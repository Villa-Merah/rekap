<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\WorkflowDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role = 'Staff', string $branch = 'Jakarta'): User
    {
        return User::factory()->create(['role' => $role, 'branch' => $branch]);
    }

    private function payload(User $recipient): array
    {
        return ['title' => 'Tugas Instagram Jakarta', 'description' => 'Kerjakan aktivitas secara manual.', 'platform' => 'Instagram', 'branch' => 'Jakarta', 'target_url' => 'https://www.instagram.com/p/contoh/', 'activities' => ['like', 'komentar'], 'recipients' => [$recipient->id], 'account_count' => 1, 'priority' => 'Normal', 'evidence_instructions' => 'Screenshot akun dan aktivitas.', 'starts_at' => now()->subHour()->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'), 'due_at' => now()->addHours(10)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i')];
    }

    private function assign(User $manager, User $recipient): object
    {
        $this->actingAs($manager)->post('/tasks', $this->payload($recipient))->assertSessionHasNoErrors()->assertRedirect();

        return DB::table('task_assignments')->orderByDesc('id')->first();
    }

    private function registerAccount(User $user, string $platform = 'Instagram'): int
    {
        $this->actingAs($user)->post('/accounts', ['platform' => $platform, 'name' => 'Akun '.$user->id, 'identity' => 'akun_'.$user->id])->assertSessionHasNoErrors();

        return DB::table('social_accounts')->where('user_id', $user->id)->value('id');
    }

    private function proof(int $account): array
    {
        return ['accounts' => [$account], 'activities' => ['like', 'komentar'], 'performed_at' => now()->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'), 'evidence_confirmation' => 1, 'notes' => 'Bukti untuk akun dan aktivitas yang dilaporkan.', 'evidence' => [$this->evidenceImage()]];
    }

    private function evidenceImage(string $name = 'bukti.png', ?int $sizeKilobytes = null): UploadedFile
    {
        $contents = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        if ($contents === false) {
            throw new \RuntimeException('Fixture gambar PNG tidak valid.');
        }

        $file = UploadedFile::fake()->createWithContent($name, $contents);

        if ($sizeKilobytes !== null) {
            $file->size($sizeKilobytes);
        }

        return $file;
    }

    public function test_assignment_submission_revision_approval_and_private_evidence(): void
    {
        Storage::fake('local');
        $manager = $this->member('Kacab');
        $staff = $this->member();
        $outsider = $this->member('Staff', 'Bandung');
        $colleague = $this->member();
        $assignment = $this->assign($manager, $staff);
        $this->assertSame(1, $staff->notifications()->count());
        $account = $this->registerAccount($staff);
        $this->post('/assignments/'.$assignment->id.'/start')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('task_assignments', ['id' => $assignment->id, 'status' => 'Dalam proses']);
        $this->post('/assignments/'.$assignment->id.'/submit', $this->proof($account))->assertSessionHasNoErrors();
        $first = DB::table('task_submissions')->first();
        $file = DB::table('task_evidence')->first();
        $this->assertCount(2, json_decode($first->reported_activities, true));
        Storage::disk('local')->assertExists($file->path);
        $this->get('/evidence/'.$file->id)->assertOk()->assertHeader('content-type', 'image/png');
        $this->actingAs($colleague)->get('/evidence/'.$file->id)->assertForbidden();
        $this->actingAs($outsider)->get('/tasks/'.$assignment->task_id)->assertForbidden();
        $this->get('/evidence/'.$file->id)->assertForbidden();
        $this->actingAs($manager)->get('/tasks/'.$assignment->task_id)->assertOk()->assertSee('Pengiriman #1');
        $this->post('/submissions/'.$first->id.'/review', ['status' => 'Perlu perbaikan'])->assertSessionHasErrors('review_notes');
        $this->post('/submissions/'.$first->id.'/review', ['status' => 'Perlu perbaikan', 'review_notes' => 'Tampilkan nama akun dengan jelas.'])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post('/assignments/'.$assignment->id.'/submit', $this->proof($account))->assertSessionHasNoErrors();
        $second = DB::table('task_submissions')->orderByDesc('id')->first();
        $this->assertSame(2, $second->revision);
        $this->assertDatabaseHas('task_submissions', ['id' => $first->id, 'status' => 'Perlu perbaikan']);
        $this->actingAs($manager)->post('/submissions/'.$first->id.'/review', ['status' => 'Disetujui'])->assertSessionHasErrors('status');
        $this->post('/submissions/'.$second->id.'/review', ['status' => 'Disetujui'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('task_assignments', ['id' => $assignment->id, 'status' => 'Disetujui']);
        $this->assertSame(3, $staff->notifications()->count());
        $this->assertSame(2, $manager->notifications()->count());
        $this->assertDatabaseCount('task_evidence', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Bukti diperiksa', 'user_id' => $manager->id]);
        $this->actingAs($staff)->get('/tasks/'.$assignment->task_id)->assertOk()->assertSee('Disetujui');
        $this->get('/tasks')->assertOk()->assertSee('1/1 penerima disetujui');
    }

    public function test_task_creation_is_restricted_by_role_branch_and_platform(): void
    {
        $manager = $this->member('Kacab');
        $staff = $this->member();
        $other = $this->member('Staff', 'Bandung');
        $payload = $this->payload($staff);
        $this->actingAs($staff)->get('/tasks/create')->assertForbidden();
        $this->post('/tasks', $payload)->assertForbidden();
        $this->actingAs($manager)->post('/tasks', [...$payload, 'branch' => 'Bandung'])->assertForbidden();
        $this->post('/tasks', [...$payload, 'recipients' => [$other->id]])->assertSessionHasErrors('recipients');
        $this->post('/tasks', [...$payload, 'activities' => ['repost']])->assertSessionHasErrors('activities');
        $this->post('/tasks', [...$payload, 'target_url' => 'javascript:alert(1)'])->assertSessionHasErrors('target_url');
        $this->post('/tasks', [...$payload, 'due_at' => $payload['starts_at']])->assertSessionHasErrors('due_at');
        $this->assertDatabaseCount('work_tasks', 0);
    }

    public function test_multiple_recipients_have_independent_status_and_filtered_task_list(): void
    {
        $manager = $this->member('Kacab');
        $one = $this->member();
        $two = $this->member();
        $data = $this->payload($one);
        $data['recipients'] = [$one->id, $two->id];
        $this->actingAs($manager)->post('/tasks', $data)->assertSessionHasNoErrors();
        $first = DB::table('task_assignments')->where('user_id', $one->id)->first();
        $this->actingAs($one)->post('/assignments/'.$first->id.'/start')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('task_assignments', ['user_id' => $two->id, 'status' => 'Belum dikerjakan']);
        $this->get('/tasks?status=Dalam%20proses')->assertSee($data['title']);
        $this->get('/tasks?status=Disetujui')->assertDontSee($data['title']);
        $this->get('/tasks?q=TidakAda')->assertSee('Belum ada tugas.');
    }

    public function test_private_task_requires_designated_supervisor_and_never_allows_self_approval(): void
    {
        Storage::fake('local');
        $admin = $this->member('Superadmin');
        $manager = $this->member('Kacab');
        $supervisor = $this->member('Direktur', 'Pusat');
        $payload = $this->payload($manager);
        $this->actingAs($manager)->post('/tasks', $payload)->assertSessionHasErrors('recipients');
        $this->actingAs($admin)->post('/supervisors', ['user_id' => $manager->id, 'supervisor_id' => $manager->id])->assertSessionHasErrors('supervisor_id');
        $this->post('/supervisors', ['user_id' => $manager->id, 'supervisor_id' => $supervisor->id])->assertSessionHasNoErrors();
        $assignment = $this->assign($manager->fresh(), $manager);
        $this->assertSame($supervisor->id, $assignment->reviewer_id);
        $account = $this->registerAccount($manager);
        $this->post('/assignments/'.$assignment->id.'/submit', $this->proof($account))->assertSessionHasNoErrors();
        $submission = DB::table('task_submissions')->first();
        $this->post('/submissions/'.$submission->id.'/review', ['status' => 'Disetujui'])->assertForbidden();
        $this->actingAs($supervisor)->get('/tasks/'.$assignment->task_id)->assertOk();
        $this->post('/submissions/'.$submission->id.'/review', ['status' => 'Disetujui'])->assertSessionHasNoErrors();
    }

    public function test_invalid_files_foreign_accounts_and_incomplete_activities_are_rejected(): void
    {
        Storage::fake('local');
        $manager = $this->member('Kacab');
        $staff = $this->member();
        $other = $this->member();
        $assignment = $this->assign($manager, $staff);
        $foreign = $this->registerAccount($other);
        $account = $this->registerAccount($staff);
        $proof = $this->proof($account);
        $this->post('/assignments/'.$assignment->id.'/submit', [...$proof, 'accounts' => [$foreign]])->assertSessionHasErrors('accounts');
        $this->post('/assignments/'.$assignment->id.'/submit', [...$proof, 'activities' => ['like']])->assertSessionHasErrors('activities');
        $this->post('/assignments/'.$assignment->id.'/submit', [...$proof, 'evidence' => [UploadedFile::fake()->create('file.svg', 1, 'image/svg+xml')]])->assertSessionHasErrors('evidence.0');
        $this->post('/assignments/'.$assignment->id.'/submit', [...$proof, 'evidence' => [$this->evidenceImage('besar.png', 6000)]])->assertSessionHasErrors('evidence.0');
        $this->post('/assignments/'.$assignment->id.'/submit', [...$proof, 'performed_at' => now()->addDay()->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i')])->assertSessionHasErrors('performed_at');
        $this->assertDatabaseCount('task_submissions', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $this->actingAs($other)->post('/assignments/'.$assignment->id.'/submit', $proof)->assertForbidden();
    }

    public function test_quota_and_account_ownership_branch_boundaries_and_pages(): void
    {
        $manager = $this->member('Kacab');
        $staff = $this->member();
        $other = $this->member('Staff', 'Bandung');
        $this->registerAccount($staff);
        $this->post('/accounts', ['platform' => 'Instagram', 'name' => 'Duplikat', 'identity' => 'akun_'.$staff->id])->assertSessionHasErrors('identity');
        $this->post('/account-quotas', ['user_id' => $staff->id, 'platform' => 'Instagram', 'required_count' => 3])->assertForbidden();
        $this->actingAs($manager)->post('/account-quotas', ['user_id' => $other->id, 'platform' => 'Instagram', 'required_count' => 3])->assertForbidden();
        $this->post('/account-quotas', ['user_id' => $staff->id, 'platform' => 'Instagram', 'required_count' => 3])->assertSessionHasNoErrors();
        $this->get('/accounts')->assertOk()->assertSee($staff->name)->assertDontSee($other->name);
        $this->get('/tasks/create')->assertOk()->assertSee('Penerima tugas');
        $this->actingAs($staff)->get('/accounts')->assertOk()->assertDontSee('Atur target akun')->assertDontSee($manager->name);
        $this->get('/users/create')->assertForbidden();
        $this->actingAs($other)->get('/tasks')->assertOk()->assertSee('Belum ada tugas.');
    }

    public function test_staff_account_targets_are_ten_per_platform_and_do_not_limit_registration(): void
    {
        $staff = $this->member();
        $response = $this->actingAs($staff)->get('/accounts')->assertOk();

        foreach (['Google/Gmail', 'YouTube', 'Instagram', 'Facebook', 'TikTok'] as $platform) {
            $response->assertSee($platform);
            $response->assertSee(
                '<tr><td>'.e($staff->name).'</td><td>'.$platform.'</td><td>10</td><td>0</td><td><span class="pill warning">10</span></td></tr>',
                false,
            );
        }

        for ($accountNumber = 1; $accountNumber <= 11; $accountNumber++) {
            $this->post('/accounts', [
                'platform' => 'Google/Gmail',
                'name' => 'Gmail '.$accountNumber,
                'identity' => 'gmail'.$accountNumber.'@example.test',
            ])->assertSessionHasNoErrors();
        }

        $this->assertSame(11, DB::table('social_accounts')->where('user_id', $staff->id)->where('platform', 'Google/Gmail')->count());
    }

    public function test_superadmin_can_add_users_with_secure_password_and_staff_account_targets(): void
    {
        $admin = $this->member('Superadmin', '');
        $payload = [
            'name' => 'Staff Baru',
            'email' => 'staff.baru@example.test',
            'role' => 'Staff',
            'branch' => 'Jakarta',
            'password' => 'password-aman-2026',
            'password_confirmation' => 'password-aman-2026',
        ];

        $response = $this->actingAs($admin)->post('/users', $payload);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertSame('Staff', $user->role);
        $this->assertSame('Jakarta', $user->branch);
        $this->assertSame(true, $user->must_change_password);
        $this->assertNotSame($payload['password'], $user->password);
        $this->assertTrue(password_verify($payload['password'], $user->password));
        $this->assertDatabaseCount('account_quotas', 5);
        $this->assertDatabaseHas('account_quotas', [
            'user_id' => $user->id,
            'platform' => 'Google/Gmail',
            'required_count' => 10,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'Pengguna ditambahkan',
            'subject_id' => $user->id,
        ]);
    }

    public function test_kacab_can_only_add_staff_to_their_own_branch(): void
    {
        $manager = $this->member('Kacab');
        $payload = [
            'name' => 'Staff Cabang',
            'email' => 'staff.cabang@example.test',
            'role' => 'Staff',
            'branch' => 'Bandung',
            'password' => 'password-aman-2026',
            'password_confirmation' => 'password-aman-2026',
        ];

        $this->actingAs($manager)->get('/users/create')->assertOk()->assertSee('Tambah pengguna');
        $this->post('/users', $payload)
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $staff = User::where('email', $payload['email'])->firstOrFail();
        $this->assertSame('Jakarta', $staff->branch);
        $this->assertDatabaseHas('account_quotas', [
            'user_id' => $staff->id,
            'platform' => 'Instagram',
            'required_count' => 10,
        ]);

        $this->post('/users', [
            ...$payload,
            'email' => 'admin.tampering@example.test',
            'role' => 'Superadmin',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'admin.tampering@example.test']);
    }

    public function test_superadmin_can_open_user_creation_menu_and_staff_cannot(): void
    {
        $admin = $this->member('Superadmin', '');
        $staff = $this->member();

        $this->actingAs($admin)->get('/users/create')->assertOk()->assertSee('Tambah pengguna');
        $this->actingAs($staff)->get('/users/create')->assertForbidden();
    }

    public function test_staff_cannot_add_users_and_initial_password_must_be_secure_and_confirmed(): void
    {
        $staff = $this->member();
        $admin = $this->member('Superadmin', '');
        $payload = [
            'name' => 'Staff Tambahan',
            'email' => 'staff.tambahan@example.test',
            'role' => 'Staff',
            'branch' => 'Jakarta',
            'password' => 'password-aman-2026',
            'password_confirmation' => 'password-aman-2026',
        ];

        $this->actingAs($staff)->post('/users', $payload)->assertForbidden();

        $this->actingAs($admin)->post('/users', [
            ...$payload,
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->post('/users', [
            ...$payload,
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    public function test_reminders_are_idempotent_and_approved_assignments_are_excluded(): void
    {
        $manager = $this->member('Kacab');
        $staff = $this->member();
        $assignment = $this->assign($manager, $staff);
        $this->artisan('tasks:remind')->assertSuccessful();
        $this->artisan('tasks:remind')->assertSuccessful();
        $this->assertSame(2, $staff->notifications()->count());
        $this->travel(11)->hours();
        $this->artisan('tasks:remind')->assertSuccessful();
        $this->artisan('tasks:remind')->assertSuccessful();
        $this->assertSame(3, $staff->notifications()->count());
        DB::table('task_assignments')->where('id', $assignment->id)->update(['status' => 'Disetujui']);
        $this->artisan('tasks:remind')->assertSuccessful();
        $this->assertSame(3, $staff->notifications()->count());
    }

    public function test_future_task_cannot_be_started_or_submitted(): void
    {
        Storage::fake('local');
        $manager = $this->member('Kacab');
        $staff = $this->member();
        $data = $this->payload($staff);
        $data['starts_at'] = now()->addDay()->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i');
        $data['due_at'] = now()->addDays(2)->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i');
        $this->actingAs($manager)->post('/tasks', $data)->assertSessionHasNoErrors();
        $assignment = DB::table('task_assignments')->first();
        $account = $this->registerAccount($staff);
        $this->post('/assignments/'.$assignment->id.'/start')->assertUnprocessable();
        $this->post('/assignments/'.$assignment->id.'/submit', $this->proof($account))->assertSessionHasErrors('performed_at');
        $this->assertDatabaseCount('task_submissions', 0);
    }

    public function test_rejection_is_final_and_duplicate_submission_or_review_cannot_overwrite_history(): void
    {
        Storage::fake('local');
        $manager = $this->member('Kacab');
        $staff = $this->member();
        $unassignedReviewer = $this->member('Kacab');
        $assignment = $this->assign($manager, $staff);
        $account = $this->registerAccount($staff);
        $this->post('/assignments/'.$assignment->id.'/submit', $this->proof($account))->assertSessionHasNoErrors();
        $this->post('/assignments/'.$assignment->id.'/submit', $this->proof($account))->assertSessionHasErrors('evidence');
        $submission = DB::table('task_submissions')->first();
        $this->actingAs($unassignedReviewer)->post('/submissions/'.$submission->id.'/review', ['status' => 'Disetujui'])->assertForbidden();
        $this->actingAs($manager)->post('/submissions/'.$submission->id.'/review', ['status' => 'Ditolak', 'review_notes' => 'Bukti tidak sesuai tugas.'])->assertSessionHasNoErrors();
        $this->post('/submissions/'.$submission->id.'/review', ['status' => 'Disetujui'])->assertSessionHasErrors('status');
        $this->actingAs($staff)->post('/assignments/'.$assignment->id.'/submit', $this->proof($account))->assertSessionHasErrors('evidence');
        $this->assertDatabaseCount('task_submissions', 1);
        $this->assertDatabaseCount('task_evidence', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertDatabaseHas('task_assignments', ['id' => $assignment->id, 'status' => 'Ditolak']);
    }

    public function test_demo_seeder_is_repeatable_and_supervisor_settings_are_admin_only(): void
    {
        $this->seed(WorkflowDemoSeeder::class);
        $this->seed(WorkflowDemoSeeder::class);
        $this->assertDatabaseCount('work_tasks', 1);
        $this->assertDatabaseCount('task_assignments', 2);
        $this->assertDatabaseCount('social_accounts', 2);
        $this->assertDatabaseCount('notifications', 2);
        $staff = User::where('email', 'arif@example.test')->firstOrFail();
        $manager = User::where('email', 'nadia@example.test')->firstOrFail();
        $admin = User::where('email', 'admin@example.test')->firstOrFail();
        $this->actingAs($staff)->post('/supervisors', ['user_id' => $staff->id, 'supervisor_id' => $manager->id])->assertForbidden();
        $this->actingAs($admin)->get('/accounts')->assertOk()->assertSee('Atasan pemeriksa tugas pribadi');
        $noBranch = User::factory()->create();
        $this->actingAs($manager)->post('/account-quotas', ['user_id' => $noBranch->id, 'platform' => 'Instagram', 'required_count' => 1])->assertForbidden();
    }
}
