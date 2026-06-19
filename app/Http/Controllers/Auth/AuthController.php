<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Support\SystemNavigation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Handles authentication flows: login, register, 2FA challenge, and logout.
 */
class AuthController extends Controller
{
    public function login(Request $request)
    {
        return inertia('Auth/LoginPage', [
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function register()
    {
        return inertia('Auth/RegisterPage');
    }

    public function processLogin(LoginRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $remember = $data['remember'] ?? false;

        $guard = Auth::guard();
        $provider = $guard->getProvider();
        $credentials = ['email' => $data['email'], 'password' => $data['password']];
        $user = $provider->retrieveByCredentials($credentials);

        if (! $user || ! $provider->validateCredentials($user, $credentials)) {
            return back()->withErrors([
                'email' => 'Email or Password is invalid',
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
            $request->session()->forget('url.intended');

            return redirect()->route('verification.notice');
        }

        return redirect()->to($this->resolveLoginRedirect($request, $user));
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
            'password' => Hash::make($data['password']),
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

    private function resolveLoginRedirect(Request $request, User $user): string
    {
        $defaultRoute = $this->defaultAuthenticatedRoute($user);
        $intendedUrl = $request->session()->pull('url.intended');

        if (! is_string($intendedUrl) || ! $this->isIntendedUrlAllowedForUser($intendedUrl, $request, $user)) {
            return $defaultRoute;
        }

        return $intendedUrl;
    }

    private function defaultAuthenticatedRoute(User $user): string
    {
        if ($user->isReviewer()) {
            return route(SystemNavigation::firstAccessibleRouteName($user, 'reviewer') ?? 'reviewer.dashboard');
        }

        if ($user->hasAdminNavigationAccess()) {
            return route('admin.dashboard');
        }

        return route('dashboard');
    }

    private function isIntendedUrlAllowedForUser(string $intendedUrl, Request $request, User $user): bool
    {
        $parts = parse_url($intendedUrl);

        if ($parts === false) {
            return false;
        }

        $host = $parts['host'] ?? null;

        if ($host !== null && $host !== $request->getHost()) {
            return false;
        }

        $path = '/'.ltrim((string) ($parts['path'] ?? '/'), '/');

        if ($this->pathStartsWith($path, '/reviewer')) {
            return $user->isReviewer();
        }

        if ($this->pathStartsWith($path, '/admin')) {
            return ! $user->isReviewer() && $user->hasAdminNavigationAccess();
        }

        if ($user->isReviewer()) {
            return false;
        }

        if ($user->hasAdminNavigationAccess()) {
            return $this->pathStartsWith($path, '/profile') || $this->pathStartsWith($path, '/notifications');
        }

        return true;
    }

    private function pathStartsWith(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix.'/');
    }
}
