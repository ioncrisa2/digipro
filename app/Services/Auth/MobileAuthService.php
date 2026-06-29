<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\MobileVerifyEmailNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;
use Spatie\Permission\Models\Role;

class MobileAuthService
{
    /**
     * @param  array{name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            Role::findOrCreate('customer', (string) config('auth.defaults.guard', 'web'));
            $user->assignRole('customer');

            return $user;
        });

        $user->notify(new MobileVerifyEmailNotification);

        return $user;
    }

    public function authenticate(string $email, string $password): ?User
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }

        return $user;
    }

    /**
     * @return array{token: NewAccessToken, expires_at: \Illuminate\Support\Carbon}
     */
    public function issueAccessToken(User $user, ?string $deviceName): array
    {
        $expiresAt = now()->addMinutes((int) config('mobile-api.token_expiration_minutes', 43_200));
        $token = $user->createToken(
            $this->resolveDeviceName($deviceName),
            ['mobile:customer'],
            $expiresAt,
        );

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
        ];
    }

    public function resolveDeviceName(?string $deviceName): string
    {
        $normalized = trim((string) $deviceName);

        return $normalized !== '' ? $normalized : 'mobile';
    }
}
