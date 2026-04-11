<?php

namespace App\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

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
        if ($this->shouldForceHttps()) {
            URL::forceScheme('https');
        }

        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verifikasi Email Akun DigiPro by KJPP HJAR')
                ->line('Klik tombol di bawah untuk mengaktifkan akun Anda.')
                ->action('Verifikasi Email', $url)
                ->line('Jika Anda tidak merasa mendaftar, abaikan email ini.');
        });
    }

    private function shouldForceHttps(): bool
    {
        $appUrl = (string) config('app.url', '');

        if (str_starts_with($appUrl, 'https://')) {
            return true;
        }

        /** @var Request|null $request */
        $request = request();

        return $request?->isSecure() === true
            || $request?->header('x-forwarded-proto') === 'https';
    }
}
