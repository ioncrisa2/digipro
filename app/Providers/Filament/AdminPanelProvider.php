<?php

namespace App\Providers\Filament;

use App\Filament\Resources\RoleResource;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Navigation\NavigationGroup;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->theme(asset('css/filament/admin/theme.css'))
            ->navigationGroups([
                NavigationGroup::make('Penilaian Properti')
                    ->icon('heroicon-o-home-modern'),
                NavigationGroup::make('Keuangan')
                    ->icon('heroicon-o-banknotes'),
                NavigationGroup::make('Konten')
                    ->icon('heroicon-o-newspaper'),
                NavigationGroup::make('Konten & Legal')
                    ->icon('heroicon-o-shield-check'),
                NavigationGroup::make('Komunikasi')
                    ->icon('heroicon-o-chat-bubble-left-right'),
                NavigationGroup::make('Master Data')
                    ->icon('heroicon-o-circle-stack'),
                NavigationGroup::make('Ref Guidelines')
                    ->icon('heroicon-o-clipboard-document-check'),
            ])
            ->pages([
                Pages\Dashboard::class,
            ])
            ->widgets([
                \App\Filament\Widgets\AppraisalRequestOverview::class,
                \App\Filament\Widgets\AppraisalRequestsNeedingAction::class,
                \App\Filament\Widgets\AppraisalRequestsPaymentPending::class,
                \App\Filament\Widgets\AppraisalRequestTodayActivity::class,
            ])
            ->resources([
                RoleResource::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\\Filament\\Clusters')
            ->databaseNotifications()
            ->topNavigation();
    }
}
