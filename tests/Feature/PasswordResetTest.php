<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $user, string $token): array
    {
        return ['email' => $user->email, 'token' => $token, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'];
    }

    public function test_guest_can_open_recovery_forms_and_login_links_to_forgot_password(): void
    {
        $token = str_repeat('a', 64);
        $this->get('/login')->assertOk()->assertSee('href="'.route('password.request').'"', false);
        $this->get('/forgot-password')->assertOk()->assertSee('Kirim tautan reset');
        $this->get('/reset-password/'.$token.'?email=test%40example.test')->assertOk()->assertSee('test@example.test')->assertSee('data-password-toggle', false);
        $this->get('/reset-password/'.$token.'?email[]=bad')->assertSessionHasErrors('email');
    }

    public function test_registered_email_receives_a_hashed_single_use_token_without_disclosing_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password')->assertSessionHasNoErrors();
        $message = session('status');
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');

            return $stored !== $notification->token && Hash::check($notification->token, $stored);
        });
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect()->assertSessionHas('status', $message);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_reset_email_uses_configured_app_url_and_indonesian_content(): void
    {
        config(['app.url' => 'https://rekap.sivmi.id']);
        $user = User::factory()->create(['name' => 'Pengguna contoh', 'email' => 'contoh+akun@example.test']);
        $mail = (new ResetPassword(str_repeat('a', 64)))->toMail($user);
        $this->assertSame('Reset kata sandi Rekap Buzzer', $mail->subject);
        $this->assertSame('Buat kata sandi baru', $mail->actionText);
        $this->assertSame('https://rekap.sivmi.id/reset-password/'.str_repeat('a', 64).'?email=contoh%2Bakun%40example.test', $mail->actionUrl);
        $this->assertStringContainsString('60 menit', implode(' ', $mail->outroLines));
    }

    public function test_successful_reset_revokes_sessions_preserves_role_and_sso_and_cannot_be_reused(): void
    {
        $user = User::factory()->create(['role' => 'Supervisor', 'branch' => 'Jakarta', 'omni_id' => 'central-user', 'must_change_password' => true, 'remember_token' => 'old-token']);
        DB::table('sessions')->insert(['id' => 'old-login', 'user_id' => $user->id, 'payload' => '', 'last_activity' => 100]);
        $token = Password::createToken($user);
        $this->post('/reset-password', [...$this->payload($user, $token), 'role' => 'Superadmin'])->assertRedirect(route('login'))->assertSessionHasNoErrors();
        $user->refresh();
        $this->assertTrue(Hash::check('new-password-123', $user->password));
        $this->assertFalse($user->must_change_password);
        $this->assertNotSame('old-token', $user->remember_token);
        $this->assertSame('Supervisor', $user->role);
        $this->assertSame('Jakarta', $user->branch);
        $this->assertSame('central-user', $user->omni_id);
        $this->assertGuest();
        $this->assertDatabaseMissing('sessions', ['id' => 'old-login']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->id, 'action' => 'Kata sandi direset melalui email']);
        $password = $user->password;
        $this->post('/reset-password', $this->payload($user, $token))->assertSessionHasErrors('email');
        $this->assertSame($password, $user->fresh()->password);
    }

    public function test_wrong_email_invalid_and_expired_tokens_cannot_change_password(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $oldPassword = $user->password;
        $otherPassword = $other->password;
        $token = Password::createToken($user);
        $this->post('/reset-password', $this->payload($other, $token))->assertSessionHasErrors('email');
        $this->assertSame($otherPassword, $other->fresh()->password);
        $this->post('/reset-password', $this->payload($user, 'wrong-token'))->assertSessionHasErrors('email');
        $this->travel(61)->minutes();
        $this->post('/reset-password', $this->payload($user, $token))->assertSessionHasErrors('email');
        $this->assertSame($oldPassword, $user->fresh()->password);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_reset_forms_validate_required_fields_and_password_confirmation(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);
        $this->post('/forgot-password', ['email' => 'invalid'])->assertSessionHasErrors('email');
        $this->post('/reset-password', [])->assertSessionHasErrors(['token', 'email', 'password']);
        $this->post('/reset-password', [...$this->payload($user, $token), 'password' => 'short'])->assertSessionHasErrors('password');
        $this->post('/reset-password', [...$this->payload($user, $token), 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->assertFalse(Hash::check('new-password-123', $user->fresh()->password));
    }

    public function test_duplicate_reset_requests_do_not_send_multiple_emails(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        $this->post('/forgot-password', ['email' => $user->email])->assertSessionHasNoErrors();
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
    }

    public function test_reset_request_route_limits_repeated_attempts(): void
    {
        for ($index = 0; $index < 5; $index++) {
            $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertRedirect();
        }
        $this->post('/forgot-password', ['email' => 'unknown@example.test'])->assertTooManyRequests();
    }

    public function test_smtp_failure_shows_a_safe_message_and_deletes_unsent_token(): void
    {
        $user = User::factory()->create();
        Notification::shouldReceive('send')->once()->andThrow(new TransportException('private-smtp-error'));
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password')
            ->assertSessionHasErrors(['email' => 'Email belum dapat dikirim. Coba kembali nanti atau hubungi pengelola.']);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }
}
