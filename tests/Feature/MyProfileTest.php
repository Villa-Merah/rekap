<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MyProfileTest extends TestCase
{
    use RefreshDatabase;

    private function assignment(User $user, string $status, string $title): int
    {
        $task = DB::table('work_tasks')->insertGetId([
            'creator_id' => $user->id, 'branch' => 'Jakarta', 'title' => $title,
            'description' => 'Aktivitas', 'platform' => 'Instagram', 'target_url' => 'https://instagram.com/p/test',
            'activities' => '["like"]', 'account_count' => 1, 'evidence_instructions' => 'Screenshot', 'priority' => 'Normal',
            'starts_at' => '2026-01-01 00:00:00', 'due_at' => '2026-01-03 00:00:00',
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]);

        return DB::table('task_assignments')->insertGetId([
            'task_id' => $task, 'user_id' => $user->id, 'reviewer_id' => $user->id,
            'status' => $status, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    public function test_profile_requires_login_and_password_change(): void
    {
        $this->get('/my-profile')->assertRedirect('/login');
        $user = User::factory()->create(['must_change_password' => true]);
        $this->actingAs($user)->get('/my-profile')->assertRedirect(route('password.change'));
    }

    public function test_profile_counts_all_history_and_only_shows_own_data_even_for_manager(): void
    {
        $user = User::factory()->create(['role' => 'Kacab', 'branch' => 'Jakarta']);
        $other = User::factory()->create(['branch' => 'Jakarta']);
        foreach (['Disetujui', 'Disetujui', 'Menunggu verifikasi', 'Dalam proses', 'Belum dikerjakan', 'Perlu perbaikan', 'Ditolak'] as $index => $status) {
            $this->assignment($user, $status, 'Tugas sendiri '.$index);
        }
        $this->assignment($other, 'Disetujui', 'Tugas rahasia orang lain');
        foreach (['Google/Gmail', 'Instagram', 'YouTube', 'TikTok'] as $platform) {
            DB::table('social_accounts')->insert(['user_id' => $user->id, 'platform' => $platform, 'name' => 'Akun '.$platform, 'identity' => 'miliksendiri', 'created_at' => '2026-01-01 00:00:00']);
        }
        DB::table('social_accounts')->insert(['user_id' => $other->id, 'platform' => 'Instagram', 'name' => 'Akun rahasia orang lain', 'identity' => 'rahasia']);
        DB::table('account_quotas')->insert(['user_id' => $user->id, 'platform' => 'Instagram', 'required_count' => 3]);
        $this->actingAs($user)->get('/my-profile?user_id='.$other->id)->assertOk()
            ->assertViewHas('summary', ['received' => 7, 'completed' => 2, 'waiting' => 1, 'in_progress' => 1, 'not_started' => 1, 'revision' => 1, 'rejected' => 1])
            ->assertViewHas('progress', 29)->assertSee('Target 3')->assertSee('Kurang 2 akun')
            ->assertSee('Akun Google/Gmail')->assertSee('Akun Instagram')->assertSee('Akun YouTube')->assertSee('Akun TikTok')
            ->assertDontSee('Tugas rahasia orang lain')->assertDontSee('Akun rahasia orang lain')->assertDontSee($user->password);
    }

    public function test_profile_shows_latest_submission_evidence_and_escapes_content(): void
    {
        $user = User::factory()->create();
        $assignment = $this->assignment($user, 'Menunggu verifikasi', '<script>alert(1)</script>');
        DB::table('task_submissions')->insert(['assignment_id' => $assignment, 'revision' => 1, 'reported_activities' => '[]', 'notes' => 'Catatan revisi lama']);
        $latest = DB::table('task_submissions')->insertGetId(['assignment_id' => $assignment, 'revision' => 2, 'reported_activities' => '["like","share"]', 'notes' => 'Catatan terbaru', 'review_notes' => 'Perbaiki gambar', 'created_at' => '2026-01-02 00:00:00']);
        $file = DB::table('task_evidence')->insertGetId(['submission_id' => $latest, 'path' => 'evidence/test.png', 'mime' => 'image/png', 'original_name' => 'bukti-terbaru.png']);
        $this->actingAs($user)->get('/my-profile')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('Catatan terbaru')->assertSee('Perbaiki gambar')
            ->assertSee('like, share')->assertSee(route('evidence.show', $file))->assertDontSee('Catatan revisi lama');
    }

    public function test_profile_renders_actual_per_account_activity_records(): void
    {
        $user = User::factory()->create();
        $assignment = $this->assignment($user, 'Menunggu verifikasi', 'Tugas dengan aktivitas nyata');
        DB::table('task_submissions')->insert([
            'assignment_id' => $assignment, 'revision' => 1,
            'reported_activities' => json_encode([
                ['account_id' => 1, 'account_name' => 'Akun satu', 'identity' => '@satu', 'activity' => 'like', 'performed_at' => '2026-01-02T00:00:00Z', 'evidence_ids' => []],
                ['account_id' => 1, 'account_name' => 'Akun satu', 'identity' => '@satu', 'activity' => 'share', 'performed_at' => '2026-01-02T00:00:00Z', 'evidence_ids' => []],
                ['account_id' => 2, 'account_name' => 'Akun dua', 'identity' => '@dua', 'activity' => 'like', 'performed_at' => '2026-01-02T00:00:00Z', 'evidence_ids' => []],
            ]),
            'created_at' => '2026-01-02 00:00:00',
        ]);
        $this->actingAs($user)->get('/my-profile')->assertOk()->assertSee('Aktivitas dilaporkan: like, share');
    }

    public function test_profile_paginates_history_without_changing_summary(): void
    {
        $user = User::factory()->create(['role' => 'Staff']);
        for ($index = 0; $index < 11; $index++) {
            $this->assignment($user, 'Disetujui', 'Tugas '.$index);
        }
        for ($index = 0; $index < 21; $index++) {
            DB::table('social_accounts')->insert(['user_id' => $user->id, 'platform' => 'Instagram', 'name' => 'Akun '.$index, 'identity' => 'identitas'.$index]);
        }
        $this->actingAs($user)->get('/my-profile')->assertOk()->assertViewHas('progress', 100)
            ->assertViewHas('assignments', fn ($items): bool => $items->count() === 10 && $items->total() === 11)
            ->assertViewHas('accounts', fn ($items): bool => $items->count() === 20 && $items->total() === 21);
        $this->get('/my-profile?tasks_page=2&accounts_page=2')->assertOk()->assertViewHas('progress', 100)
            ->assertViewHas('assignments', fn ($items): bool => $items->count() === 1)
            ->assertViewHas('accounts', fn ($items): bool => $items->count() === 1);
    }

    public function test_empty_superadmin_profile_has_zero_progress_and_no_personal_report_link(): void
    {
        $user = User::factory()->create(['role' => 'Superadmin']);
        $this->actingAs($user)->get('/my-profile')->assertOk()->assertViewHas('progress', 0)
            ->assertSee('Belum ada tugas yang diterima.')->assertSee('Belum ada akun yang didaftarkan.')
            ->assertDontSee('Laporan aktivitas &amp; PDF', false);
    }
}
