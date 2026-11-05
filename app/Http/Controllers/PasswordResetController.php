<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WorkNotice;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('password.forgot');
    }

    public function email(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => 'required|string|email|max:255']);
        try {
            Password::sendResetLink($data);
        } catch (TransportExceptionInterface $exception) {
            if ($user = Password::getUser($data)) {
                Password::deleteToken($user);
            }
            Log::error('Pengiriman email pemulihan kata sandi gagal.', ['exception' => $exception::class]);

            return back()->withInput($request->only('email'))->withErrors(['email' => 'Email belum dapat dikirim. Coba kembali nanti atau hubungi pengelola.']);
        }

        return back()->withInput($request->only('email'))->with('status', 'Jika email terdaftar, tautan reset akan dikirim. Periksa kotak masuk dan folder spam.');
    }

    public function form(Request $request, string $token): Response
    {
        $data = $request->validate(['email' => 'nullable|string|email|max:255']);

        return response()->view('password.reset', ['token' => $token, 'email' => $data['email'] ?? ''])
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|string|email|max:255', 'token' => 'required|string|max:256',
            'password' => 'required|string|min:8|confirmed',
        ]);
        $status = Password::reset($data, function (User $user, string $password): void {
            DB::transaction(function () use ($user, $password): void {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'must_change_password' => false])->save();
                DB::table('sessions')->where('user_id', $user->id)->delete();
                WorkNotice::audit($user->id, 'Kata sandi direset melalui email', 'user', $user->id);
            });
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'Tautan reset tidak valid atau sudah kedaluwarsa. Minta tautan baru melalui Lupa kata sandi.']);
        }

        return redirect()->route('login')->with('status', 'Kata sandi berhasil direset. Silakan masuk menggunakan kata sandi baru.');
    }
}
