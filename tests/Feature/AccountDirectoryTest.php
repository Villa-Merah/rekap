<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AccountDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $owner, string $name = 'Akun contoh', string $platform = 'Instagram'): void
    {
        DB::table('social_accounts')->insert(['user_id' => $owner->id, 'platform' => $platform, 'name' => $name,
            'identity' => 'identitas-'.$name, 'created_at' => '2026-11-04 05:00:00']);
    }

    public function test_login_and_password_change_are_required(): void
    {
        $this->get('/accounts/list')->assertRedirect('/login');
        $user = User::factory()->create(['must_change_password' => true]);
        $this->actingAs($user)->get('/accounts/list')->assertRedirect(route('password.change'));
    }

    public function test_staff_only_sees_own_accounts_and_search_cannot_escape_scope(): void
    {
        $user = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta']);
        $other = User::factory()->create(['branch' => 'Jakarta']);
        $this->account($user, 'Akun sendiri');
        $this->account($other, 'Rahasia orang lain');
        $this->actingAs($user)->get('/accounts/list')->assertOk()->assertSee('Akun sendiri')->assertDontSee('Rahasia orang lain');
        $this->get('/accounts/list?q=Rahasia&user_id='.$other->id)->assertOk()->assertViewHas('accounts', fn ($accounts): bool => $accounts->total() === 0);
        $this->get('/accounts')->assertOk()->assertSee(route('accounts.list'))->assertDontSee('<h2>Akun terdaftar</h2>', false);
    }

    public function test_kacab_only_sees_branch_accounts(): void
    {
        $manager = User::factory()->create(['role' => 'Kacab', 'branch' => 'Jakarta']);
        $staff = User::factory()->create(['role' => 'Staff', 'branch' => 'Jakarta']);
        $outside = User::factory()->create(['role' => 'Staff', 'branch' => 'Bandung']);
        $this->account($staff, 'Akun Jakarta');
        $this->account($outside, 'Akun Bandung');
        $this->actingAs($manager)->get('/accounts/list?branch=Bandung')->assertOk()->assertSee('Akun Jakarta')->assertDontSee('Akun Bandung');
    }

    #[TestWith(['Superadmin'])]
    #[TestWith(['Supervisor'])]
    public function test_global_roles_can_view_accounts_across_branches(string $role): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $this->account(User::factory()->create(['branch' => 'Jakarta']), 'Akun Jakarta');
        $this->account(User::factory()->create(['branch' => 'Bandung']), 'Akun Bandung');
        $this->actingAs($actor)->get('/accounts/list')->assertOk()->assertSee('Akun Jakarta')->assertSee('Akun Bandung');
    }

    public function test_search_platform_filter_and_pagination_preserve_the_current_query(): void
    {
        $user = User::factory()->create(['role' => 'Staff']);
        for ($index = 0; $index < 80; $index++) {
            $this->account($user, 'Pencarian '.$index);
        }
        $this->account($user, 'Pencarian Youtube', 'YouTube');
        $this->actingAs($user)->get('/accounts/list?q=Pencarian&platform=Instagram&page=2')->assertOk()
            ->assertViewHas('accounts', fn ($accounts): bool => $accounts->total() === 80 && $accounts->count() === 20 && $accounts->currentPage() === 2)
            ->assertSee('Menampilkan 21–40 dari 80 hasil')->assertSee('Sebelumnya')->assertSee('Berikutnya')
            ->assertSee('aria-current="page"', false)->assertSee('q=Pencarian', false)->assertSee('platform=Instagram', false)
            ->assertDontSee('Pencarian Youtube')->assertDontSee('<svg', false);
        $this->get('/accounts/list?q=Pencarian&platform=Instagram&page=4')->assertOk()->assertSee('Menampilkan 61–80 dari 80 hasil')
            ->assertSee('<span class="rb-page rb-page-disabled" aria-disabled="true">Berikutnya', false);
    }

    public function test_filters_are_validated_and_account_content_is_escaped(): void
    {
        $user = User::factory()->create();
        $this->account($user, '<script>alert(1)</script>');
        DB::table('social_accounts')->where('user_id', $user->id)->update(['profile_url' => 'javascript:alert(1)']);
        $this->actingAs($user)->get('/accounts/list')->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('href="javascript:', false);
        $this->get('/accounts/list?platform=Unknown')->assertSessionHasErrors('platform');
        $this->get('/accounts/list?q='.str_repeat('x', 101))->assertSessionHasErrors('q');
    }
}
