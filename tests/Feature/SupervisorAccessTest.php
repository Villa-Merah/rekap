<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupervisorAccessTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $recipients, string $branch = '*'): array
    {
        return ['title' => 'Tugas lintas cabang', 'description' => 'Aktivitas manual', 'platform' => 'Instagram',
            'branch' => $branch, 'target_url' => 'https://instagram.com/p/test', 'activities' => ['like'],
            'recipients' => $recipients, 'account_count' => 1, 'priority' => 'Normal', 'evidence_instructions' => 'Screenshot',
            'starts_at' => now()->subHour()->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i'),
            'due_at' => now()->addDay()->setTimezone('Asia/Jakarta')->format('Y-m-d\TH:i')];
    }

    public function test_supervisor_can_broadcast_and_view_tasks_across_branches_without_global_admin_access(): void
    {
        $supervisor = User::factory()->create(['role' => 'Supervisor', 'branch' => null]);
        $jakarta = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta']);
        $bandung = User::factory()->create(['role' => 'Staff', 'branch' => 'Bandung']);
        $kacab = User::factory()->create(['role' => 'Kacab', 'branch' => 'Jakarta']);
        $this->actingAs($supervisor)->get('/tasks/create')->assertOk()->assertSee('Semua cabang')->assertSee($bandung->name);
        $this->post('/tasks', $this->payload([$jakarta->id, $bandung->id]))->assertRedirect(route('tasks.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('work_tasks', 2);
        $this->assertDatabaseCount('task_assignments', 2);
        foreach ([$jakarta, $bandung] as $staff) {
            $task = DB::table('work_tasks')->where('branch', $staff->branch)->first();
            $this->assertDatabaseHas('task_assignments', ['task_id' => $task->id, 'user_id' => $staff->id, 'reviewer_id' => $supervisor->id]);
            $this->get('/tasks/'.$task->id)->assertOk()->assertSee($staff->name);
            $this->get('/reports?user_id='.$staff->id)->assertOk()->assertSee($staff->name);
            $this->assertSame(1, $staff->notifications()->count());
        }
        $this->get('/users')->assertForbidden();
        $this->post('/users', [])->assertForbidden();
        $this->post('/account-quotas', ['user_id' => $jakarta->id, 'platform' => 'Instagram', 'required_count' => 5])->assertForbidden();
        $this->post('/supervisors', ['user_id' => $jakarta->id, 'supervisor_id' => $supervisor->id])->assertForbidden();
        $bandungTask = DB::table('work_tasks')->where('branch', 'Bandung')->first();
        $this->actingAs($kacab)->get('/tasks/'.$bandungTask->id)->assertForbidden();
        $this->post('/tasks', $this->payload([$jakarta->id, $bandung->id]))->assertForbidden();
    }

    public function test_supervisor_can_verify_other_reviewers_assignments_but_superadmin_cannot(): void
    {
        $supervisor = User::factory()->create(['role' => 'Supervisor']);
        $kacab = User::factory()->create(['role' => 'Kacab', 'branch' => 'Bandung']);
        $staff = User::factory()->create(['role' => 'Staff', 'branch' => 'Bandung']);
        $admin = User::factory()->create(['role' => 'Superadmin']);
        $this->actingAs($kacab)->post('/tasks', $this->payload([$staff->id], 'Bandung'))->assertSessionHasNoErrors();
        $assignment = DB::table('task_assignments')->first();
        DB::table('task_assignments')->where('id', $assignment->id)->update(['status' => 'Menunggu verifikasi']);
        $submission = DB::table('task_submissions')->insertGetId(['assignment_id' => $assignment->id, 'revision' => 1, 'reported_activities' => '[]', 'created_at' => now()]);
        $this->actingAs($admin)->get('/tasks')->assertOk()->assertDontSee('+ Buat tugas');
        $this->get('/tasks/create')->assertForbidden();
        $this->post('/accounts', [])->assertForbidden();
        $this->post('/assignments/'.$assignment->id.'/start')->assertForbidden();
        $this->post('/assignments/'.$assignment->id.'/submit', [])->assertForbidden();
        $this->post('/tasks', $this->payload([$staff->id], 'Bandung'))->assertForbidden();
        $this->post('/submissions/'.$submission.'/review', ['status' => 'Disetujui'])->assertForbidden();
        $this->assertDatabaseHas('task_assignments', ['id' => $assignment->id, 'status' => 'Menunggu verifikasi']);
        $this->actingAs($supervisor)->get('/tasks?status=Menunggu%20verifikasi')->assertOk()->assertSee('Tugas lintas cabang');
        $this->get('/tasks/'.$assignment->task_id)->assertOk()->assertSee('Buka verifikasi bukti')->assertDontSee('Simpan hasil pemeriksaan');
        $this->get('/verifications/'.$assignment->id)->assertOk()->assertSee('Simpan hasil pemeriksaan');
        $this->post('/submissions/'.$submission.'/review', ['status' => 'Disetujui'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('task_assignments', ['id' => $assignment->id, 'status' => 'Disetujui']);
        $this->assertDatabaseHas('task_submissions', ['id' => $submission, 'reviewer_id' => $supervisor->id]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $supervisor->id, 'action' => 'Bukti diperiksa']);
    }

    public function test_only_superadmin_can_create_supervisor_and_designate_supervisor_role_as_reviewer(): void
    {
        $admin = User::factory()->create(['role' => 'Superadmin']);
        $staff = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta']);
        $director = User::factory()->create(['role' => 'Direktur']);
        $this->actingAs($admin)->post('/users', ['name' => 'Supervisor pusat', 'email' => 'supervisor@example.test', 'role' => 'Supervisor',
            'password' => 'initial-password-123', 'password_confirmation' => 'initial-password-123'])->assertRedirect()->assertSessionHasNoErrors();
        $supervisor = User::where('email', 'supervisor@example.test')->firstOrFail();
        $this->assertSame('Supervisor', $supervisor->role);
        $this->assertNull($supervisor->branch);
        $this->post('/supervisors', ['user_id' => $staff->id, 'supervisor_id' => $director->id])->assertSessionHasErrors('supervisor_id');
        $this->post('/supervisors', ['user_id' => $staff->id, 'supervisor_id' => $supervisor->id])->assertSessionHasNoErrors();
        $this->assertSame($supervisor->id, $staff->refresh()->supervisor_id);
    }

    public function test_supervisor_cannot_approve_own_task(): void
    {
        $supervisor = User::factory()->create(['role' => 'Supervisor', 'branch' => 'Jakarta']);
        $reviewer = User::factory()->create(['role' => 'Supervisor']);
        $supervisor->forceFill(['supervisor_id' => $reviewer->id])->save();
        $this->actingAs($supervisor)->post('/tasks', $this->payload([$supervisor->id], 'Jakarta'))->assertSessionHasNoErrors();
        $assignment = DB::table('task_assignments')->first();
        DB::table('task_assignments')->where('id', $assignment->id)->update(['status' => 'Menunggu verifikasi']);
        $submission = DB::table('task_submissions')->insertGetId(['assignment_id' => $assignment->id, 'revision' => 1, 'reported_activities' => '[]', 'created_at' => now()]);
        $this->post('/submissions/'.$submission.'/review', ['status' => 'Disetujui'])->assertForbidden();
        $this->assertDatabaseHas('task_assignments', ['id' => $assignment->id, 'status' => 'Menunggu verifikasi']);
    }
}
