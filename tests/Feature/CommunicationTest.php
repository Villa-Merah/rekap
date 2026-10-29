<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommunicationTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $branch = 'Jakarta', string $role = 'Staff'): User
    {
        return User::factory()->create(['branch' => $branch, 'role' => $role]);
    }

    public function test_login_dashboard_and_logout(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'correct-password'])->assertRedirect('/');
        $this->get('/')->assertOk()->assertSee('Komunikasi tim');
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_new_user_must_change_initial_password_before_accessing_the_workspace(): void
    {
        $user = User::factory()->create([
            'password' => 'initial-password',
            'must_change_password' => true,
        ]);

        $this->get('/password/change')->assertRedirect(route('login'));

        $this->post('/login', ['email' => $user->email, 'password' => 'initial-password'])
            ->assertRedirect(route('password.change'));
        $this->get('/')
            ->assertRedirect(route('password.change'));
        $this->get('/password/change')
            ->assertOk()
            ->assertSee('Ganti kata sandi awal.');

        $this->put('/password/change', [
            'current_password' => 'initial-password',
            'password' => 'new-pass',
            'password_confirmation' => 'new-pass',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertSame(false, $user->must_change_password);
        $this->assertSame(true, password_verify('new-pass', $user->password));
        $this->get('/')->assertOk();
    }

    public function test_password_change_requires_current_password_and_a_different_confirmed_password(): void
    {
        $user = User::factory()->create([
            'password' => 'initial-password',
            'must_change_password' => true,
        ]);
        $this->actingAs($user);

        $this->put('/password/change', [
            'current_password' => 'wrong-password',
            'password' => 'new-secure-password-2026',
            'password_confirmation' => 'new-secure-password-2026',
        ])->assertSessionHasErrors('current_password');

        $this->put('/password/change', [
            'current_password' => 'initial-password',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');

        $this->put('/password/change', [
            'current_password' => 'initial-password',
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ])->assertSessionHasErrors('password');

        $user->refresh();
        $this->assertSame(true, $user->must_change_password);
        $this->assertSame(true, password_verify('initial-password', $user->password));

        $this->put('/password/change', [
            'current_password' => 'initial-password',
            'password' => 'initial-password',
            'password_confirmation' => 'initial-password',
        ])->assertSessionHasErrors('password');

        $user->refresh();
        $this->assertSame(true, $user->must_change_password);
    }

    public function test_message_notification_and_read_receipt(): void
    {
        $sender = $this->member();
        $recipient = $this->member();
        $this->actingAs($sender)->postJson('/chat/'.$recipient->id, ['body' => '<script>alert(1)</script>'])->assertCreated();
        $notification = $recipient->notifications()->firstOrFail();
        $this->assertSame('chat', $notification->type);
        $this->assertSame(0, $sender->notifications()->count());
        $this->actingAs($recipient)->getJson('/communication')->assertJsonPath('unread', 1)->assertJsonPath('contacts.0.unread', 1);
        $this->getJson('/chat/'.$sender->id)->assertOk()->assertJsonCount(1, 'messages');
        $this->postJson('/chat/'.$sender->id.'/read', ['through' => $notification->data['message_id']])->assertOk();
        $this->assertNotNull(DB::table('messages')->first()->read_at);
        $this->assertSame(0, $recipient->unreadNotifications()->count());
    }

    public function test_cross_branch_contacts_and_chat_are_blocked(): void
    {
        $user = $this->member();
        $other = $this->member('Bandung');
        $this->actingAs($user)->getJson('/communication')->assertJsonCount(0, 'contacts');
        $this->getJson('/chat/'.$other->id)->assertForbidden();
        $this->postJson('/chat/'.$other->id, ['body' => 'Hello'])->assertForbidden();
        $this->postJson('/chat/'.$other->id.'/read', ['through' => 999])->assertForbidden();
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_superadmin_can_contact_across_branches(): void
    {
        $admin = $this->member('Pusat', 'Superadmin');
        $staff = $this->member('Bandung');
        $director = $this->member('Jakarta', 'Direktur');
        $this->actingAs($admin)->postJson('/chat/'.$staff->id, ['body' => 'Halo'])->assertCreated();
        $this->actingAs($staff)->postJson('/chat/'.$admin->id, ['body' => 'Baik'])->assertCreated();
        $this->actingAs($director)->getJson('/chat/'.$staff->id)->assertForbidden();
    }

    public function test_third_user_cannot_read_conversation_or_mark_others_notifications(): void
    {
        $sender = $this->member();
        $recipient = $this->member();
        $third = $this->member();
        $this->actingAs($sender)->postJson('/chat/'.$recipient->id, ['body' => 'Rahasia'])->assertCreated();
        $notification = $recipient->notifications()->firstOrFail();
        $this->actingAs($third)->getJson('/chat/'.$sender->id)->assertJsonCount(0, 'messages');
        $this->postJson('/notifications/read', ['ids' => [$notification->id]])->assertOk();
        $this->assertNull($notification->fresh()->read_at);
        $this->getJson('/communication')->assertJsonPath('unread', 0)->assertJsonCount(0, 'notifications');
    }

    public function test_empty_oversized_and_self_messages_are_rejected(): void
    {
        $sender = $this->member();
        $recipient = $this->member();
        $this->actingAs($sender)->postJson('/chat/'.$recipient->id, ['body' => '   '])->assertUnprocessable();
        $this->postJson('/chat/'.$recipient->id, ['body' => str_repeat('a', 4001)])->assertUnprocessable();
        $this->postJson('/chat/'.$sender->id, ['body' => 'Hello'])->assertForbidden();
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_read_cursor_preserves_newer_messages(): void
    {
        $sender = $this->member();
        $recipient = $this->member();
        $first = $this->actingAs($sender)->postJson('/chat/'.$recipient->id, ['body' => 'Satu'])->json('id');
        $second = $this->postJson('/chat/'.$recipient->id, ['body' => 'Dua'])->json('id');
        $this->actingAs($recipient)->postJson('/chat/'.$sender->id.'/read', ['through' => $first])->assertOk();
        $this->assertDatabaseHas('messages', ['id' => $second, 'read_at' => null]);
        $this->assertSame(1, $recipient->unreadNotifications()->count());
    }

    public function test_pagination_and_guest_access(): void
    {
        $this->getJson('/communication')->assertUnauthorized();
        $sender = $this->member();
        $recipient = $this->member();
        for ($i = 1; $i <= 55; $i++) {
            DB::table('messages')->insert(['sender_id' => $sender->id, 'recipient_id' => $recipient->id, 'body' => 'Pesan '.$i, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($recipient)->getJson('/chat/'.$sender->id)->assertJsonCount(50, 'messages')->assertJsonPath('messages.0.body', 'Pesan 6');
        $this->getJson('/chat/'.$sender->id.'?before=6')->assertJsonCount(5, 'messages');
    }
}
