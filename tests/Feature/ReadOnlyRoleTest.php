<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class ReadOnlyRoleTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['Direktur Utama'])]
    #[TestWith(['Direktur'])]
    #[TestWith(['Komisaris'])]
    public function test_executive_roles_can_read_but_cannot_modify_data(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'branch' => 'Jakarta']);
        $this->actingAs($user);
        foreach (['/tasks', '/accounts', '/my-profile', '/reports', '/activity-logs', '/guide', '/'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/accounts')->assertDontSee('Daftarkan akun pribadi');
        $this->get('/my-profile')->assertDontSee('Daftarkan akun');
        $this->get('/')->assertSee('pengiriman pesan dinonaktifkan');
        foreach (['/tasks', '/accounts', '/account-quotas', '/users', '/supervisors', '/assignments/1/start', '/assignments/1/submit', '/submissions/1/review', '/chat/'.$user->id] as $url) {
            $this->post($url, [])->assertForbidden();
        }
        $this->put('/users/'.$user->id, [])->assertForbidden();
        $this->delete('/users/'.$user->id)->assertForbidden();
        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertDatabaseCount('work_tasks', 0);
        $this->assertDatabaseCount('messages', 0);
        $this->assertModelExists($user);
    }

    #[TestWith(['Direktur Utama'])]
    #[TestWith(['Direktur'])]
    #[TestWith(['Komisaris'])]
    public function test_executive_roles_can_change_password_and_logout(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'password' => 'initial-password-123', 'must_change_password' => true]);
        $this->actingAs($user)->get('/tasks')->assertRedirect(route('password.change'));
        $this->put('/password/change', ['current_password' => 'initial-password-123', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'])->assertRedirect(route('dashboard'));
        $this->assertFalse($user->refresh()->must_change_password);
        $this->post('/notifications/read', ['ids' => ['00000000-0000-4000-8000-000000000001']])->assertSuccessful();
        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_staff_can_still_register_accounts(): void
    {
        $user = User::factory()->create(['role' => 'Staff']);
        $this->actingAs($user)->post('/accounts', ['platform' => 'Instagram', 'name' => 'Akun staf', 'identity' => '@staf'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'identity' => '@staf']);
    }
}
