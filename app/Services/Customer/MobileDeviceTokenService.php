<?php

namespace App\Services\Customer;

use App\Models\MobileDeviceToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MobileDeviceTokenService
{
    public function register(User $user, array $data): MobileDeviceToken
    {
        return DB::transaction(function () use ($user, $data): MobileDeviceToken {
            $tokenHash = hash('sha256', $data['token']);
            $record = MobileDeviceToken::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first() ?? new MobileDeviceToken;

            $record->fill([
                'user_id' => $user->id,
                'token' => $data['token'],
                'token_hash' => $tokenHash,
                'platform' => $data['platform'],
                'provider' => $data['provider'] ?? 'fcm',
                'device_name' => $data['device_name'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'os_version' => $data['os_version'] ?? null,
                'locale' => $data['locale'] ?? null,
                'last_seen_at' => now(),
            ])->save();

            return $record->refresh();
        });
    }

    public function remove(User $user, string $token): void
    {
        $user->mobileDeviceTokens()
            ->where('token_hash', hash('sha256', $token))
            ->delete();
    }
}
