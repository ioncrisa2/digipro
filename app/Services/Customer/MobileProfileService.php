<?php

namespace App\Services\Customer;

use App\Models\User;
use App\Notifications\MobileVerifyEmailNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MobileProfileService
{
    public function load(User $user): User
    {
        return $user->load([
            'billingProvince:id,name',
            'billingRegency:id,name',
            'billingDistrict:id,name',
            'billingVillage:id,name',
        ]);
    }

    public function update(User $user, array $data): User
    {
        $emailChanged = mb_strtolower($user->email) !== mb_strtolower($data['email']);

        $user->fill($data);

        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null]);
        }

        $user->save();

        if ($emailChanged) {
            $user->notify(new MobileVerifyEmailNotification);
        }

        return $this->load($user->refresh());
    }

    public function verifyPassword(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password lama tidak sesuai.'],
            ]);
        }
    }

    public function updatePassword(User $user, array $data): void
    {
        $this->verifyPassword($user, $data['current_password']);
        $user->update(['password' => $data['password']]);
    }

    public function updateAvatar(User $user, UploadedFile $avatar): User
    {
        $path = $avatar->store('avatars', 'public');
        $oldPath = $user->getRawOriginal('avatar_url');

        $user->update(['avatar_url' => $path]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('public')->delete($oldPath);
        }

        return $this->load($user->refresh());
    }

    public function removeAvatar(User $user): User
    {
        $oldPath = $user->getRawOriginal('avatar_url');
        $user->update(['avatar_url' => null]);

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }

        return $this->load($user->refresh());
    }
}
