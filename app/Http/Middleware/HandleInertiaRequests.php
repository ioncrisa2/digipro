<?php

namespace App\Http\Middleware;

use App\Support\SystemNavigation;
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
                    'phone_number' => $request->user()->phone_number,
                    'whatsapp_number' => $request->user()->whatsapp_number,
                    'address' => $request->user()->address,
                    'company_name' => $request->user()->company_name,
                    'billing_address' => $request->user()->billing_address,
                    'billing_recipient_name' => $request->user()->billing_recipient_name,
                    'billing_province_id' => $request->user()->billing_province_id,
                    'billing_regency_id' => $request->user()->billing_regency_id,
                    'billing_district_id' => $request->user()->billing_district_id,
                    'billing_village_id' => $request->user()->billing_village_id,
                    'billing_postal_code' => $request->user()->billing_postal_code,
                    'billing_address_detail' => $request->user()->billing_address_detail,
                    'billing_npwp' => $request->user()->billing_npwp,
                    'billing_nik' => $request->user()->billing_nik,
                    'billing_email' => $request->user()->billing_email,
                    'avatar_url' => $request->user()->avatar_url
                        ? Storage::disk('public')->url($request->user()->avatar_url)
                        : null,
                    'roles' => $request->user()->getRoleNames()->values()->all(),
                    'is_admin' => $request->user()->hasAdminAccess(),
                    'is_reviewer' => $request->user()->isReviewer(),
                    'system_section_permissions' => $request->user()->systemSectionPermissions(),
                    'two_factor_enabled' => ! is_null($request->user()->two_factor_secret),
                    'two_factor_confirmed_at' => $request->user()->two_factor_confirmed_at
                        ? $request->user()->two_factor_confirmed_at->toDateTimeString()
                        : null,
                ]
                : null,

            'navigation.reviewer_nav' => fn () => $request->user()
                ? SystemNavigation::navForUser($request->user(), 'reviewer')
                : [],
            'navigation.admin_nav' => fn () => $request->user()
                ? SystemNavigation::navForUser($request->user(), 'admin')
                : [],

            //flash message
            'flash' => [
                'status' => fn() => $request->session()->get('status'),
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
            ],

            //notification count
            'unreadCount' => fn() => rescue(
                fn () => $request->user()
                    ? $request->user()->unreadNotifications()->count()
                    : 0,
                0,
                report: false
            ),

            //notification
            'notifications' => fn() => rescue(
                fn () => $request->user()
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
                [],
                report: false
            ),

            'termsDocument' => fn() => rescue(
                fn () => TermsDocument::active()
                    ->orderByDesc('published_at')
                    ->first()
                    ?->toPublicArray(),
                null,
                report: false
            ),

            'blogNavCategories' => fn () => rescue(
                fn () => $request->routeIs('articles.*')
                    ? ArticleCategory::query()
                        ->active()
                        ->showInNav()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->get(['name', 'slug'])
                    : [],
                [],
                report: false
            ),
        ];
    }
}
