<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Illuminate\Support\Facades\Storage;
use App\Models\TermsDocument;
use App\Models\ArticleCategory;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            //auth user
            'auth.user' => fn() => $request->user()
                ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'avatar_url' => $request->user()->avatar_url
                        ? Storage::disk('public')->url($request->user()->avatar_url)
                        : null,
                    'roles' => $request->user()->getRoleNames()->values()->all(),
                    'is_admin' => $request->user()->hasAdminAccess(),
                    'is_reviewer' => $request->user()->isReviewer(),
                    'two_factor_enabled' => ! is_null($request->user()->two_factor_secret),
                    'two_factor_confirmed_at' => $request->user()->two_factor_confirmed_at
                        ? $request->user()->two_factor_confirmed_at->toDateTimeString()
                        : null,
                ]
                : null,

            //flash message
            'flash' => [
                'status' => fn() => $request->session()->get('status'),
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
            ],

            //notification count
            'unreadCount' => fn() => $request->user()
                ? $request->user()->unreadNotifications()->count()
                : 0,

            //notification
            'notifications' => fn() => $request->user()
                ? $request->user()->notifications()
                ->latest()
                ->limit(15)
                ->get()
                ->map(fn($n) => [
                    'id' => $n->id,
                    'title' => data_get($n->data, 'title', 'Notifikasi'),
                    'message' => data_get($n->data, 'message', ''),
                    'url' => data_get($n->data, 'url'),
                    'read' => ! is_null($n->read_at),
                    'time' => optional($n->created_at)->diffForHumans(),
                ])
                : [],

            'termsDocument' => fn() => TermsDocument::active()
                ->orderByDesc('published_at')
                ->first()
                ?->toPublicArray(),

            'blogNavCategories' => fn () => $request->routeIs('articles.*')
                ? ArticleCategory::query()
                    ->active()
                    ->showInNav()
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['name', 'slug'])
                : [],
        ];
    }
}
