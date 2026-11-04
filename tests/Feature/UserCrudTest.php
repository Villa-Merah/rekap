<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    private function input(User $member, array $changes = []): array
    {
        return array_replace(['name' => $member->name, 'email' => $member->email, 'role' => $member->role, 'branch' => $member->branch], $changes);
    }

    public function test_guest_and_staff_cannot_manage_users(): void
    {
        $member = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta']);
        $this->get('/users/'.$member->id.'/edit')->assertRedirect('/login');
        $this->put('/users/'.$member->id, [])->assertRedirect('/login');
        $this->delete('/users/'.$member->id)->assertRedirect('/login');
        $this->actingAs($member)->get('/users/'.$member->id.'/edit')->assertForbidden();
        $this->put('/users/'.$member->id, $this->input($member))->assertForbidden();
        $this->delete('/users/'.$member->id, ['confirmation' => $member->email])->assertForbidden();
        $this->assertModelExists($member);
    }

    public function test_admin_updates_identity_and_role_without_resetting_existing_password_or_targets(): void
    {
        $admin = User::factory()->create(['role' => 'Superadmin']);
        $member = User::factory()->create(['role' => 'Marketing', 'branch' => 'Jakarta', 'omni_id' => 'sso-id']);
        DB::table('account_quotas')->insert(['user_id' => $member->id, 'platform' => 'Instagram', 'required_count' => 4]);
        $password = $member->password;
        $this->actingAs($admin)->get('/users/'.$member->id.'/edit')->assertOk()->assertSee($member->email)->assertDontSee($password);
        $this->put('/users/'.$member->id, $this->input($member, ['name' => 'Nama baru', 'role' => 'Staff', 'branch' => 'Bandung']))->assertRedirect(route('users.index'));
        $member->refresh();
        $this->assertSame('Nama baru', $member->name);
        $this->assertSame('Bandung', $member->branch);
        $this->assertSame($password, $member->password);
        $this->assertSame('sso-id', $member->omni_id);
        $this->assertDatabaseHas('account_quotas', ['user_id' => $member->id, 'platform' => 'Instagram', 'required_count' => 4]);
        $this->assertDatabaseHas('account_quotas', ['user_id' => $member->id, 'platform' => 'TikTok', 'required_count' => 10]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Pengguna diperbarui', 'subject_id' => $member->id]);
    }

    public function test_email_and_password_changes_clear_sso_and_sessions_and_require_password_change(): void
    {
        $admin = User::factory()->create(['role' => 'Superadmin']);
        $member = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta', 'omni_id' => 'central', 'remember_token' => 'old-token']);
        DB::table('sessions')->insert(['id' => 'old-session', 'user_id' => $member->id, 'payload' => '', 'last_activity' => 123]);
        DB::table('password_reset_tokens')->insert(['email' => $member->email, 'token' => 'old']);
        $oldEmail = $member->email;
        $this->actingAs($admin)->put('/users/'.$member->id, $this->input($member, [
            'email' => 'new@example.test', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123',
            'omni_id' => 'attacker', 'supervisor_id' => $admin->id,
        ]))->assertRedirect(route('users.index'));
        $member->refresh();
        $this->assertNull($member->omni_id);
        $this->assertNull($member->email_verified_at);
        $this->assertNull($member->remember_token);
        $this->assertNull($member->supervisor_id);
        $this->assertTrue($member->must_change_password);
        $this->assertTrue(Hash::check('new-password-123', $member->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'old-session']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $oldEmail]);
    }

    public function test_kacab_can_only_manage_staff_or_marketing_in_own_branch(): void
    {
        $manager = User::factory()->create(['role' => 'Kacab', 'branch' => 'Jakarta']);
        $staff = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta']);
        $outside = User::factory()->create(['role' => 'Staff', 'branch' => 'Bandung']);
        $director = User::factory()->create(['role' => 'Direktur', 'branch' => 'Jakarta']);
        $this->actingAs($manager)->get('/users/'.$staff->id.'/edit')->assertOk();
        $this->put('/users/'.$staff->id, $this->input($staff, ['role' => 'Marketing', 'branch' => 'Bandung']))->assertRedirect(route('users.index'));
        $this->assertSame('Jakarta', $staff->refresh()->branch);
        $this->put('/users/'.$staff->id, $this->input($staff, ['role' => 'Superadmin']))->assertSessionHasErrors('role');
        foreach ([$outside, $director, $manager] as $blocked) {
            $this->get('/users/'.$blocked->id.'/edit')->assertNotFound();
            $this->put('/users/'.$blocked->id, $this->input($blocked))->assertNotFound();
            $this->delete('/users/'.$blocked->id, ['confirmation' => $blocked->email])->assertNotFound();
            $this->assertModelExists($blocked);
        }
        $this->delete('/users/'.$staff->id, ['confirmation' => $staff->email])->assertRedirect(route('users.index'));
        $this->assertModelMissing($staff);
    }

    public function test_validation_and_self_protection_keep_user_unchanged(): void
    {
        $admin = User::factory()->create(['role' => 'Superadmin']);
        $member = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta']);
        $this->actingAs($admin)->put('/users/'.$member->id, $this->input($member, ['email' => $admin->email, 'password' => 'short', 'password_confirmation' => 'mismatch']))->assertSessionHasErrors(['email', 'password']);
        $this->put('/users/'.$member->id, $this->input($member, ['role' => 'Unknown', 'branch' => '']))->assertSessionHasErrors('role');
        $this->put('/users/'.$member->id, $this->input($member, ['branch' => '']))->assertSessionHasErrors('branch');
        $this->put('/users/'.$admin->id, $this->input($admin, ['role' => 'Staff', 'branch' => 'Jakarta']))->assertSessionHasErrors('role');
        $this->delete('/users/'.$admin->id, ['confirmation' => $admin->email])->assertForbidden();
        $this->assertSame('Superadmin', $admin->refresh()->role);
        $this->assertSame($member->email, $member->fresh()->email);
    }

    public function test_delete_requires_confirmation_and_cleans_related_account_data(): void
    {
        $admin = User::factory()->create(['role' => 'Superadmin']);
        $member = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta']);
        DB::table('social_accounts')->insert(['user_id' => $member->id, 'platform' => 'Instagram', 'name' => 'Test', 'identity' => 'test']);
        DB::table('account_quotas')->insert(['user_id' => $member->id, 'platform' => 'Instagram', 'required_count' => 10]);
        $this->actingAs($admin)->delete('/users/'.$member->id, ['confirmation' => 'wrong@example.test'])->assertSessionHasErrors('confirmation');
        $this->assertModelExists($member);
        $this->delete('/users/'.$member->id, ['confirmation' => $member->email])->assertRedirect(route('users.index'));
        $this->assertModelMissing($member);
        $this->assertDatabaseMissing('social_accounts', ['user_id' => $member->id]);
        $this->assertDatabaseMissing('account_quotas', ['user_id' => $member->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Pengguna dihapus', 'subject_id' => $member->id]);
    }

    public function test_user_with_task_history_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'Superadmin']);
        $member = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta']);
        $task = DB::table('work_tasks')->insertGetId(['creator_id' => $admin->id, 'branch' => 'Jakarta', 'title' => 'Tugas',
            'description' => 'Test', 'platform' => 'Instagram', 'target_url' => 'https://instagram.com', 'activities' => '["like"]',
            'account_count' => 1, 'evidence_instructions' => 'Bukti', 'priority' => 'Normal', 'starts_at' => now(), 'due_at' => now()->addDay()]);
        DB::table('task_assignments')->insert(['task_id' => $task, 'user_id' => $member->id, 'reviewer_id' => $admin->id]);
        $this->actingAs($admin)->delete('/users/'.$member->id, ['confirmation' => $member->email])->assertSessionHasErrors([
            'confirmation' => 'Pengguna memiliki riwayat tugas atau pemeriksaan dan tidak dapat dihapus.',
        ]);
        $this->assertModelExists($member);
        $this->assertDatabaseHas('task_assignments', ['task_id' => $task, 'user_id' => $member->id]);
    }
}
