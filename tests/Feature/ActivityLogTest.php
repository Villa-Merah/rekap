<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    private function record(?User $actor, string $action, string $date = '2026-11-03 05:00:00', array $details = []): void
    {
        DB::table('audit_logs')->insert(['user_id' => $actor?->id, 'action' => $action, 'subject_type' => 'user', 'subject_id' => 1,
            'details' => json_encode($details), 'created_at' => $date, 'updated_at' => $date]);
    }

    public function test_guest_and_password_change_required_users_cannot_view_logs(): void
    {
        $this->get('/activity-logs')->assertRedirect('/login');
        $user = User::factory()->create(['must_change_password' => true]);
        $this->actingAs($user)->get('/activity-logs')->assertRedirect(route('password.change'));
    }

    public function test_staff_only_sees_own_logs_even_when_searching_for_other_users(): void
    {
        $staff = User::factory()->create(['role' => 'Staff']);
        $other = User::factory()->create();
        $this->record($staff, 'Bukti dikirim');
        $this->record($other, 'Aktivitas rahasia');
        $this->actingAs($staff)->get('/activity-logs')->assertOk()->assertSee('Bukti dikirim')->assertDontSee('Aktivitas rahasia');
        $this->get('/activity-logs?action=Aktivitas%20rahasia&user_id='.$other->id)->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 0);
    }

    public function test_kacab_only_sees_current_branch_and_excludes_superadmin_and_deleted_users(): void
    {
        $manager = User::factory()->create(['role' => 'Kacab', 'branch' => 'Jakarta']);
        $staff = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta']);
        $outside = User::factory()->create(['role' => 'Staff', 'branch' => 'Bandung']);
        $admin = User::factory()->create(['role' => 'Superadmin', 'branch' => 'Jakarta']);
        $this->record($staff, 'Aksi cabang sendiri');
        $this->record($outside, 'Aksi cabang luar');
        $this->record($admin, 'Aksi superadmin');
        $this->record(null, 'Aksi pengguna terhapus');
        $this->actingAs($manager)->get('/activity-logs?branch=Bandung')->assertOk()->assertSee('Aksi cabang sendiri')
            ->assertDontSee('Aksi cabang luar')->assertDontSee('Aksi superadmin')->assertDontSee('Aksi pengguna terhapus');
    }

    public function test_superadmin_filters_local_dates_and_search_and_sees_deleted_user_logs(): void
    {
        $admin = User::factory()->create(['role' => 'Superadmin']);
        $staff = User::factory()->create(['name' => 'Anggota Bandung', 'branch' => 'Bandung']);
        $this->record($staff, 'Bukti dikirim', '2026-11-02 17:00:00');
        $this->record($staff, 'Bukti dikirim', '2026-11-02 16:59:59');
        $this->record(null, 'Pengguna dihapus');
        $this->actingAs($admin)->get('/activity-logs')->assertOk()->assertSee('Pengguna sudah dihapus')
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 3);
        $this->get('/activity-logs?q=Bandung&action=Bukti%20dikirim&from=2026-11-03&to=2026-11-03')->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1)->assertSee('03 Nov 2026 00:00:00');
        $this->get('/activity-logs?to=2026-11-02')->assertOk()
            ->assertViewHas('logs', fn ($logs): bool => $logs->total() === 1);
    }

    public function test_logs_escape_content_hide_sensitive_details_and_preserve_pagination_filters(): void
    {
        $admin = User::factory()->create(['role' => 'Superadmin']);
        for ($index = 0; $index < 26; $index++) {
            $this->record($admin, '<script>alert(1)</script>', details: ['role' => 'Staff', 'password' => 'secret-password', 'review_notes' => 'private-note', 'client_secret' => 'secret-client']);
        }
        $this->actingAs($admin)->get('/activity-logs?q=script')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('Jabatan: Staff')
            ->assertDontSee('secret-password')->assertDontSee('private-note')->assertDontSee('secret-client')
            ->assertSee('q=script', false)->assertViewHas('logs', fn ($logs): bool => $logs->count() === 25 && $logs->total() === 26);
        $this->get('/activity-logs?q=script&page=2')->assertOk()->assertViewHas('logs', fn ($logs): bool => $logs->count() === 1);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/activity-logs?from=invalid')->assertSessionHasErrors('from');
        $this->get('/activity-logs?from=2026-11-04&to=2026-11-01')->assertSessionHasErrors('to');
        $this->get('/activity-logs?q='.str_repeat('x', 101))->assertSessionHasErrors('q');
    }

    public function test_login_logout_and_password_change_are_recorded_without_secrets(): void
    {
        $user = User::factory()->create(['password' => 'old-password-123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('audit_logs', 0);
        $this->post('/login', ['email' => $user->email, 'password' => 'old-password-123'])->assertRedirect(route('dashboard'));
        $this->put('/password/change', ['current_password' => 'old-password-123', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertRedirect(route('dashboard'));
        $this->post('/logout')->assertRedirect(route('login'));
        foreach (['Masuk melalui email', 'Kata sandi diganti', 'Keluar dari aplikasi'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => $action, 'details' => '[]']);
        }
    }
}
