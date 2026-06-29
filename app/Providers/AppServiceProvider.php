<?php

namespace App\Providers;

use App\Contracts\DigitalSignatureProvider;
use App\Services\Peruri\PeruriSignItProvider;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DigitalSignatureProvider::class, PeruriSignItProvider::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureMobileApiRateLimiters();

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

    private function configureMobileApiRateLimiters(): void
    {
        RateLimiter::for('mobile-auth-register', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('register|'.$request->ip()));

        RateLimiter::for('mobile-auth-login', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('login|'.Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('mobile-auth-two-factor', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('two-factor|'.hash('sha256', (string) $request->input('challenge_token')).'|'.$request->ip()));

        RateLimiter::for('mobile-auth-password', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('password|'.Str::lower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('mobile-auth-verification', fn (Request $request): Limit => Limit::perMinute(6)
            ->by('verification|'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
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
