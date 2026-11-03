<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OmniClient;
use App\Services\WorkNotice;
use DeveloperAwam\OmniCentralAuth\Http\Controllers\Server\AuthorizationController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class OmniLoginController extends Controller
{
    public function prepare(Request $request): JsonResponse
    {
        abort_unless(OmniClient::ready(), 503, 'Login SSO belum diaktifkan oleh administrator.');
        $authorizationUrl = Socialite::driver('omni')->redirect()->getTargetUrl();
        $request->session()->put('omni.started_at', now()->timestamp);

        return response()->json([
            'authorization_url' => $authorizationUrl,
            'callback_url' => config('omni-central-auth.client.redirect_uri'),
            'server_origin' => OmniClient::serverOrigin(),
            'state' => $request->session()->get('state'),
        ])->header('Cache-Control', 'no-store');
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! OmniClient::ready()) {
            return $this->failed('Login SSO belum diaktifkan oleh administrator.');
        }
        $state = $request->session()->pull('state');
        $startedAt = $request->session()->pull('omni.started_at');
        try {
            $input = $request->validate([
                'state' => 'required|string|max:128',
                'sso_data' => 'required|string|max:16000',
            ]);
        } catch (ValidationException) {
            return $this->failed('Respons login SSO tidak lengkap. Silakan masuk kembali.');
        }
        if (! is_string($state) || ! hash_equals($state, $input['state']) || ! is_int($startedAt) || $startedAt < now()->timestamp - 300) {
            return $this->failed('Sesi login SSO tidak valid atau kedaluwarsa. Silakan masuk kembali.');
        }
        $payload = AuthorizationController::decryptPayload($input['sso_data'], config('omni-central-auth.client.signing_key'));
        $validator = Validator::make($payload ?? [], [
            'omni_id' => ['required', function (string $attribute, mixed $value, \Closure $fail): void {
                if ((! is_string($value) && ! is_int($value)) || strlen((string) $value) > 128 || (string) $value === '') {
                    $fail('Identitas SSO tidak valid.');
                }
            }],
            'email' => 'required|string|email|max:255',
            'timestamp' => 'required|integer',
        ]);
        if ($validator->fails() || $payload['timestamp'] < now()->timestamp - 300 || $payload['timestamp'] > now()->timestamp + 30) {
            return $this->failed('Data login SSO tidak valid atau kedaluwarsa. Silakan coba kembali.');
        }
        if (! Cache::add('omni-used:'.hash('sha256', $input['sso_data']), true, now()->addMinutes(6))) {
            return $this->failed('Data login SSO sudah digunakan. Silakan mulai login baru.');
        }
        $member = DB::transaction(function () use ($payload): ?User {
            $remoteId = (string) $payload['omni_id'];
            $member = User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower($payload['email'])])->lockForUpdate()->first();
            if (! $member || ($member->omni_id !== null && $member->omni_id !== $remoteId)
                || User::query()->where('omni_id', $remoteId)->where('id', '!=', $member->id)->exists()) {
                return null;
            }
            if ($member->omni_id === null) {
                $member->forceFill(['omni_id' => $remoteId])->save();
            }
            WorkNotice::audit($member->id, 'Masuk melalui SSO', 'user', $member->id);

            return $member;
        });
        if (! $member) {
            return $this->failed('Akun SSO belum dapat dihubungkan. Hubungi Kacab atau Superadmin untuk memeriksa akun organisasi Anda.');
        }
        Auth::login($member);
        $request->session()->regenerate();
        $response = $member->must_change_password
            ? redirect()->route('password.change')
            : redirect()->intended(route('dashboard'));

        return $response->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['sso' => $message])
            ->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
