<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\PasswordUpdateRequest;
use App\Support\SystemNavigation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

/**
 * Handles profile updates, avatar management, and password changes.
 */
class ProfileController extends Controller
{
    public function edit(Request $request)
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
        ]);
    }

    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();

        $user->update($request->validated());

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

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

    public function removeAvatar(Request $request)
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

    public function verifyCurrentPassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'Password lama tidak sesuai.',
            ]);
        }

        return back()->with('success', 'Password lama sesuai.');
    }
}
