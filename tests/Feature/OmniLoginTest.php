<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use DeveloperAwam\OmniCentralAuth\Http\Controllers\Server\AuthorizationController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OmniLoginTest extends TestCase
{
    use RefreshDatabase;

    private function enableSso(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-04 08:00:00', 'UTC'));
        config([
            'omni-central-auth.enabled' => true,
            'omni-central-auth.client.server_url' => 'https://sivmi.id',
            'omni-central-auth.client.client_id' => 'test-client-id',
            'omni-central-auth.client.client_secret' => 'test-client-secret',
            'omni-central-auth.client.redirect_uri' => 'http://localhost/omni/callback',
            'omni-central-auth.client.signing_key' => 'test-only-shared-signing-key',
        ]);
    }

    private function payload(string $email, array $overrides = []): string
    {
        return AuthorizationController::encryptPayload([
            'omni_id' => 123, 'name' => 'Central User', 'email' => $email,
            'timestamp' => now()->timestamp, ...$overrides,
        ], config('omni-central-auth.client.signing_key'));
    }

    private function pending(): static
    {
        return $this->withSession(['state' => 'test-session-state', 'omni' => ['started_at' => now()->timestamp]]);
    }

    public function test_unconfigured_sso_keeps_local_login_and_rejects_preparation(): void
    {
        config(['omni-central-auth.enabled' => false]);
        $this->get('/login')->assertOk()->assertSee('Masuk ke ruang kerja')->assertDontSee('id="omni-login"', false);
        $this->postJson('/omni/prepare')->assertStatus(503)->assertJsonPath('message', 'Login SSO belum diaktifkan oleh administrator.');
        $this->get('/register')->assertNotFound();
        $this->get('/omni-dashboard')->assertNotFound();
        $this->post('/oauth/token')->assertNotFound();
    }

    public function test_configured_sso_prepares_popup_with_state_and_without_exposing_secrets(): void
    {
        $this->enableSso();
        $this->get('/login')->assertOk()->assertSee('Masuk dengan akun SIVMI');
        $response = $this->postJson('/omni/prepare')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('server_origin', 'https://sivmi.id')
            ->assertJsonPath('callback_url', 'http://localhost/omni/callback');
        $url = $response->json('authorization_url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('https://sivmi.id/omni/authorize', strstr($url, '?', true));
        $this->assertSame('test-client-id', $query['client_id']);
        $this->assertSame($response->json('state'), $query['state']);
        $this->assertStringNotContainsString('test-client-secret', $response->getContent());
        $this->assertStringNotContainsString('test-only-shared-signing-key', $response->getContent());
    }

    public function test_sso_links_existing_account_without_changing_role_branch_or_password(): void
    {
        $this->enableSso();
        $staff = User::factory()->create(['email' => 'staff@example.test', 'role' => 'Staff', 'branch' => 'Jakarta']);
        $password = $staff->password;
        $name = $staff->name;
        $this->pending()->get('/omni/callback?'.http_build_query([
            'state' => 'test-session-state',
            'sso_data' => $this->payload('STAFF@example.test', ['role' => 'Superadmin', 'branch' => 'Bandung']),
        ]))->assertRedirect(route('dashboard'))->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertAuthenticatedAs($staff);
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'omni_id' => '123', 'role' => 'Staff', 'branch' => 'Jakarta', 'password' => $password, 'name' => $name]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $staff->id, 'action' => 'Masuk melalui SSO']);
    }

    public function test_unknown_central_account_is_not_registered_automatically(): void
    {
        $this->enableSso();
        $this->pending()->get('/omni/callback?'.http_build_query([
            'state' => 'test-session-state', 'sso_data' => $this->payload('unknown@example.test'),
        ]))->assertRedirect('/login')->assertSessionHasErrors('sso');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_callback_without_matching_session_state_cannot_log_in(): void
    {
        $this->enableSso();
        $staff = User::factory()->create(['email' => 'staff@example.test']);
        $this->pending()->get('/omni/callback?'.http_build_query([
            'state' => 'wrong-state', 'sso_data' => $this->payload($staff->email),
        ]))->assertRedirect('/login')->assertSessionHasErrors('sso');
        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'omni_id' => null]);
    }

    public function test_expired_payload_cannot_log_in(): void
    {
        $this->enableSso();
        $staff = User::factory()->create();
        $this->pending()->get('/omni/callback?'.http_build_query([
            'state' => 'test-session-state', 'sso_data' => $this->payload($staff->email, ['timestamp' => now()->timestamp - 301]),
        ]))->assertRedirect('/login')->assertSessionHasErrors('sso');
        $this->assertGuest();
    }

    public function test_invalid_encrypted_payload_cannot_log_in(): void
    {
        $this->enableSso();
        $this->pending()->get('/omni/callback?state=test-session-state&sso_data=invalid-payload')
            ->assertRedirect('/login')->assertSessionHasErrors('sso');
        $this->assertGuest();
    }

    public function test_a_central_identity_cannot_link_to_two_local_accounts(): void
    {
        $this->enableSso();
        User::factory()->create(['omni_id' => '123']);
        $staff = User::factory()->create(['omni_id' => null]);
        $this->pending()->get('/omni/callback?'.http_build_query([
            'state' => 'test-session-state', 'sso_data' => $this->payload($staff->email),
        ]))->assertRedirect('/login')->assertSessionHasErrors('sso');
        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'omni_id' => null]);
    }

    public function test_existing_link_cannot_be_replaced_by_another_central_identity(): void
    {
        $this->enableSso();
        $staff = User::factory()->create(['omni_id' => '456']);
        $this->pending()->get('/omni/callback?'.http_build_query([
            'state' => 'test-session-state', 'sso_data' => $this->payload($staff->email),
        ]))->assertRedirect('/login')->assertSessionHasErrors('sso');
        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'omni_id' => '456']);
    }

    public function test_used_payload_cannot_be_replayed_with_a_new_login_session(): void
    {
        $this->enableSso();
        $staff = User::factory()->create();
        $payload = $this->payload($staff->email);
        $this->pending()->get('/omni/callback?'.http_build_query(['state' => 'test-session-state', 'sso_data' => $payload]))->assertRedirect();
        $this->post('/logout')->assertRedirect('/login');
        $this->pending()->get('/omni/callback?'.http_build_query(['state' => 'test-session-state', 'sso_data' => $payload]))
            ->assertRedirect('/login')->assertSessionHasErrors('sso');
        $this->assertGuest();
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'Masuk melalui SSO')->count());
    }

    public function test_sso_does_not_bypass_required_local_password_change(): void
    {
        $this->enableSso();
        $staff = User::factory()->create(['must_change_password' => true]);
        $this->pending()->get('/omni/callback?'.http_build_query([
            'state' => 'test-session-state', 'sso_data' => $this->payload($staff->email),
        ]))->assertRedirect(route('password.change'));
        $this->assertAuthenticatedAs($staff);
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'must_change_password' => true]);
    }

    public function test_incomplete_credentials_do_not_enable_sso(): void
    {
        $this->enableSso();
        config(['omni-central-auth.client.signing_key' => '']);
        $this->get('/login')->assertOk()->assertDontSee('id="omni-login"', false);
        $this->postJson('/omni/prepare')->assertStatus(503);
    }

    public function test_incomplete_callback_returns_a_visible_login_error(): void
    {
        $this->enableSso();
        $this->pending()->get('/omni/callback')->assertRedirect('/login')->assertSessionHasErrors(['sso' => 'Respons login SSO tidak lengkap. Silakan masuk kembali.']);

        $this->assertGuest();
    }

    public function test_expired_login_session_cannot_accept_a_fresh_payload(): void
    {
        $this->enableSso();
        $staff = User::factory()->create();
        $this->withSession(['state' => 'test-session-state', 'omni' => ['started_at' => now()->timestamp - 301]])
            ->get('/omni/callback?'.http_build_query(['state' => 'test-session-state', 'sso_data' => $this->payload($staff->email)]))
            ->assertRedirect('/login')->assertSessionHasErrors('sso');
        $this->assertGuest();
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'omni_id' => null]);
    }
}
