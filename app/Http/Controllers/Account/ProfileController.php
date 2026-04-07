<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AccountAccessRequest;
use App\Http\Requests\Account\PasswordUpdateRequest;
use App\Http\Requests\Account\ProfileUpdateRequest;
use App\Http\Requests\Account\UpdateAvatarRequest;
use App\Http\Requests\Account\VerifyCurrentPasswordRequest;
use App\Support\SupportContact;
use App\Support\SystemNavigation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Handles profile updates, avatar management, and password changes.
 */
class ProfileController extends Controller
{
    public function edit(AccountAccessRequest $request)
    {
        $user = $request->user();
        $isReviewer = (bool) $user?->hasRole('Reviewer');
        $isAdmin = (bool) $user && SystemNavigation::hasContextAccess($user, 'admin');

        if ($isReviewer && $request->routeIs('profile.edit')) {
            return redirect()->route('reviewer.profile.edit');
        }

        return inertia('Profile/Index', [
            'layoutContext' => $isReviewer ? 'reviewer' : ($isAdmin ? 'admin' : 'customer'),
            'profileRoutes' => [
                'edit' => $isReviewer ? route('reviewer.profile.edit') : route('profile.edit'),
                'update' => $isReviewer ? route('reviewer.profile.update') : route('profile.update'),
                'password' => $isReviewer ? route('reviewer.profile.password') : route('profile.password'),
                'passwordVerify' => $isReviewer ? route('reviewer.profile.password.verify') : route('profile.password.verify'),
                'avatar' => $isReviewer ? route('reviewer.profile.avatar') : route('profile.avatar'),
                'avatarRemove' => $isReviewer ? route('reviewer.profile.avatar.remove') : route('profile.avatar.remove'),
            ],
            'supportContact' => SupportContact::payload(),
        ]);
    }

    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();

        $user->update($request->validated());

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updateAvatar(UpdateAvatarRequest $request)
    {
        $user = $request->user();
        $file = $request->file('avatar');

        $path = $file->store('avatars', 'public');

        $oldPath = $user->getRawOriginal('avatar_url');
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $user->update(['avatar_url' => $path]);

        return back()->with('success', 'Foto profil berhasil diperbarui.');
    }

    public function removeAvatar(AccountAccessRequest $request)
    {
        $user = $request->user();

        $oldPath = $user->getRawOriginal('avatar_url');
        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $user->update(['avatar_url' => null]);

        return back()->with('success', 'Foto profil dihapus.');
    }

    public function updatePassword(PasswordUpdateRequest $request)
    {
        $user = $request->user();

        // cek password lama
        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'Password lama tidak sesuai.',
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password berhasil diperbarui.');
    }

    public function verifyCurrentPassword(VerifyCurrentPasswordRequest $request)
    {
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'Password lama tidak sesuai.',
            ]);
        }

        return back()->with('success', 'Password lama sesuai.');
    }
}
