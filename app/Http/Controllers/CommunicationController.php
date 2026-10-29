<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class CommunicationController extends Controller
{
    public function loginForm(): View
    {
        return view('login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'Email atau kata sandi tidak sesuai.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        if ($request->user()->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function changePassword(): View
    {
        return view('password.change');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', Password::min(8), 'confirmed', 'different:current_password'],
        ]);

        $request->user()->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
        ])->save();

        return redirect()->route('dashboard')->with('success', 'Kata sandi berhasil diganti.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function index(): View
    {
        return view('communications');
    }

    private function authorizeContact(User $actor, User $contact): void
    {
        abort_unless($actor->id !== $contact->id && ($actor->role === 'Superadmin' || $contact->role === 'Superadmin' || ($actor->branch !== null && $actor->branch === $contact->branch)), 403);
    }

    public function feed(Request $request): JsonResponse
    {
        $actor = $request->user();
        $contacts = User::query()->where('id', '!=', $actor->id)
            ->when($actor->role !== 'Superadmin', function ($query) use ($actor) {
                $query->where(function ($query) use ($actor) {
                    $query->where('role', 'Superadmin');
                    if ($actor->branch !== null) {
                        $query->orWhere('branch', $actor->branch);
                    }
                });
            })->orderBy('name')->get(['id', 'name', 'branch', 'role']);
        $unread = DB::table('messages')->where('recipient_id', $actor->id)->whereNull('read_at')->selectRaw('sender_id, count(*) as total')->groupBy('sender_id')->pluck('total', 'sender_id');

        return response()->json([
            'contacts' => $contacts->map(fn ($contact) => [...$contact->toArray(), 'unread' => $unread[$contact->id] ?? 0]),
            'notifications' => $actor->notifications()->limit(50)->get(),
            'unread' => $actor->unreadNotifications()->count(),
            'latest_message_id' => DB::table('messages')->where('recipient_id', $actor->id)->max('id') ?? 0,
        ]);
    }

    public function conversation(Request $request, User $user): JsonResponse
    {
        $this->authorizeContact($request->user(), $user);
        $actorId = $request->user()->id;
        $messages = DB::table('messages')->where(function ($query) use ($actorId, $user) {
            $query->where(fn ($q) => $q->where('sender_id', $actorId)->where('recipient_id', $user->id))
                ->orWhere(fn ($q) => $q->where('sender_id', $user->id)->where('recipient_id', $actorId));
        })->when($request->query('before'), fn ($q, $before) => $q->where('id', '<', (int) $before))->orderByDesc('id')->limit(50)->get()->reverse()->values();

        return response()->json(['messages' => $messages]);
    }

    public function send(Request $request, User $user): JsonResponse
    {
        $this->authorizeContact($request->user(), $user);
        $data = $request->validate(['body' => ['required', 'string', 'max:4000', function ($attribute, $value, $fail) {
            if (trim($value) === '') {
                $fail('Pesan tidak boleh kosong.');
            }
        }]]);
        $id = DB::transaction(function () use ($request, $user, $data) {
            $id = DB::table('messages')->insertGetId(['sender_id' => $request->user()->id, 'recipient_id' => $user->id, 'body' => trim($data['body']), 'created_at' => now(), 'updated_at' => now()]);
            $user->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'chat', 'data' => ['title' => 'Pesan dari '.$request->user()->name, 'body' => Str::limit(trim($data['body']), 120), 'sender_id' => $request->user()->id, 'message_id' => $id]]);

            return $id;
        });

        return response()->json(['id' => $id], 201);
    }

    public function readConversation(Request $request, User $user): JsonResponse
    {
        $this->authorizeContact($request->user(), $user);
        $data = $request->validate(['through' => 'required|integer|min:0']);
        DB::table('messages')->where('recipient_id', $request->user()->id)->where('sender_id', $user->id)->where('id', '<=', $data['through'])->whereNull('read_at')->update(['read_at' => now()]);
        $request->user()->unreadNotifications()->where('type', 'chat')->where('data->sender_id', $user->id)->where('data->message_id', '<=', $data['through'])->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }

    public function readNotifications(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array|max:50', 'ids.*' => 'uuid']);
        $request->user()->unreadNotifications()->whereIn('id', $data['ids'])->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
