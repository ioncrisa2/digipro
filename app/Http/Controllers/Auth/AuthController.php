<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Support\SystemNavigation;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Auth\Events\Registered;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Models\Role;

/**
 * Handles authentication flows: login, register, 2FA challenge, and logout.
 */
class AuthController extends Controller
{
    public function login()
    {
        return inertia('Auth/LoginPage');
    }

    public function register()
    {
        return inertia('Auth/RegisterPage');
    }

    public function processLogin(LoginRequest $request)
    {
        $data = $request->validated();

        $remember = $data['remember'] ?? false;

        $guard = Auth::guard();
        $provider = $guard->getProvider();
        $credentials = ['email' => $data['email'], 'password' => $data['password']];
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user || ! $provider->validateCredentials($user, $credentials)) {
            return back()->withErrors([
                'email' => 'Email or Password is invalid'
            ])->onlyInput('email');
        }

        if ($user->two_factor_secret &&
            $user->two_factor_confirmed_at &&
            in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user))) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $remember,
            ]);

            return redirect()->route('two-factor.login');
        }

        $guard->login($user, $remember);
        $request->session()->regenerate();

        if (! $user->hasVerifiedEmail()) {
            return redirect()->intended(route('verification.notice'));
        }

        $defaultRoute = $user->isReviewer()
            ? route(SystemNavigation::firstAccessibleRouteName($user, 'reviewer') ?? 'reviewer.dashboard')
            : ($user->hasAdminNavigationAccess()
                ? route('admin.dashboard')
                : route('dashboard'));

        return redirect()->intended($defaultRoute);
    }

    public function twoFactorChallenge()
    {
        if (! request()->session()->has('login.id')) {
            return redirect()->route('login');
        }

        $user = User::find(request()->session()->get('login.id'));

        return inertia('Auth/TwoFactorChallenge', [
            'email' => $user?->email,
        ]);
    }

    public function processRegister(RegisterRequest $request)
    {
        $data = $request->validated();

        $guardName = config('auth.defaults.guard', 'web');

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password'])
        ]);

        Role::findOrCreate('customer', $guardName);
        $user->assignRole('customer');

        event(new Registered($user));

        return redirect()->route('login')->with('success', 'You are registered!! Please Login');
    }

    public function logout()
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
