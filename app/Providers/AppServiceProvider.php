<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('work.pagination');
        $resetUrl = fn ($user, string $token): string => rtrim(config('app.url'), '/').'/reset-password/'.rawurlencode($token).'?'.http_build_query(['email' => $user->getEmailForPasswordReset()]);
        ResetPassword::createUrlUsing($resetUrl);
        ResetPassword::toMailUsing(function ($user, string $token) use ($resetUrl): MailMessage {
            $url = $resetUrl($user, $token);

            return (new MailMessage)->subject('Reset kata sandi Rekap Buzzer')->greeting('Halo, '.$user->name)
                ->line('Kami menerima permintaan reset kata sandi untuk akun Rekap Buzzer Anda.')
                ->action('Buat kata sandi baru', $url)
                ->line('Tautan ini berlaku selama '.config('auth.passwords.users.expire').' menit dan hanya dapat digunakan sekali.')
                ->line('Jika Anda tidak meminta reset kata sandi, abaikan email ini. Kata sandi Anda tetap sama.')
                ->salutation('Tim Rekap Buzzer');
        });
    }
}
