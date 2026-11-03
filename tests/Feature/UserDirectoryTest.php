<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class UserDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_must_sign_in_to_view_users(): void
    {
        $this->get('/users')->assertRedirect('/login');
    }

    #[TestWith(['Staff'])]
    #[TestWith(['Direktur'])]
    public function test_users_without_management_access_cannot_view_directory(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'branch' => 'Jakarta']);
        $this->actingAs($user)->get('/users')->assertForbidden();
    }

    public function test_kacab_only_sees_own_branch_even_with_a_different_branch_filter(): void
    {
        $manager = User::factory()->create(['role' => 'Kacab', 'branch' => 'Jakarta']);
        $staff = User::factory()->create(['name' => 'Anggota Jakarta', 'role' => 'Staff', 'branch' => 'Jakarta']);
        $outside = User::factory()->create(['name' => 'Anggota Bandung', 'role' => 'Staff', 'branch' => 'Bandung']);
        $this->actingAs($manager)->get('/users')->assertOk()->assertSee($staff->email)->assertDontSee($outside->email);
        $this->get('/users?branch=Bandung')->assertOk()->assertDontSee($outside->email)
            ->assertViewHas('users', fn ($users): bool => $users->total() === 0);
    }

    public function test_superadmin_searches_by_email_and_filters_role_and_branch(): void
    {
        $admin = User::factory()->create(['role' => 'Superadmin']);
        $match = User::factory()->create(['name' => 'Anggota Cocok', 'email' => 'target@example.test', 'role' => 'Staff', 'branch' => 'Bandung']);
        $other = User::factory()->create(['name' => 'Anggota Lain', 'role' => 'Marketing', 'branch' => 'Jakarta']);
        $this->actingAs($admin)->get('/users')->assertOk()->assertSee($match->email)->assertSee($other->email);
        $this->get('/users?q=target%40example.test&role=Staff&branch=Bandung')->assertOk()
            ->assertSee($match->name)->assertDontSee($other->email)
            ->assertViewHas('users', fn ($users): bool => $users->total() === 1);
    }

    public function test_directory_does_not_load_passwords_and_escapes_user_content(): void
    {
        $admin = User::factory()->create(['role' => 'Superadmin']);
        $staff = User::factory()->create(['name' => '<script>alert(1)</script>', 'role' => 'Staff', 'branch' => 'Jakarta', 'must_change_password' => true, 'omni_id' => 'central-user']);
        $this->actingAs($admin)->get('/users')->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Wajib ganti kata sandi awal')->assertSee('SSO terhubung')->assertDontSee($staff->password)
            ->assertViewHas('users', fn ($users): bool => ! array_key_exists('password', $users->first()->getAttributes()));
    }

    public function test_pagination_preserves_search_and_creation_page_links_back_to_users(): void
    {
        $admin = User::factory()->create(['role' => 'Superadmin']);
        User::factory()->count(21)->create(['name' => 'Staf pencarian', 'role' => 'Staff', 'branch' => 'Jakarta']);
        $this->actingAs($admin)->get('/users?q=Staf&role=Staff')->assertOk()
            ->assertViewHas('users', fn ($users): bool => $users->count() === 20 && $users->total() === 21)
            ->assertSee('q=Staf', false);
        $this->get('/users?q=Staf&role=Staff&page=2')->assertOk()
            ->assertViewHas('users', fn ($users): bool => $users->count() === 1);
        $this->get('/users/create')->assertOk()->assertSee('href="'.route('users.index').'"', false);
    }
}
