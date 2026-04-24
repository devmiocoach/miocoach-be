<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Register;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->registration(Register::class)
            ->emailVerification()
            ->colors(['primary' => Color::Amber])
            ->pages([Dashboard::class])
            ->widgets([AccountWidget::class, FilamentInfoWidget::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);

        // Discovery automatica delle risorse Filament da ogni modulo
        foreach (glob(app_path('Modules/*/src/Filament/Resources'), GLOB_ONLYDIR) ?: [] as $path) {
            preg_match('#Modules/(\w+)/src/Filament/Resources#', $path, $m);
            if (isset($m[1])) {
                $panel->discoverResources(
                    in: $path,
                    for: "App\\Modules\\{$m[1]}\\Filament\\Resources",
                );
            }
        }

        foreach (glob(app_path('Modules/*/src/Filament/Pages'), GLOB_ONLYDIR) ?: [] as $path) {
            preg_match('#Modules/(\w+)/src/Filament/Pages#', $path, $m);
            if (isset($m[1])) {
                $panel->discoverPages(
                    in: $path,
                    for: "App\\Modules\\{$m[1]}\\Filament\\Pages",
                );
            }
        }

        foreach (glob(app_path('Modules/*/src/Filament/Widgets'), GLOB_ONLYDIR) ?: [] as $path) {
            preg_match('#Modules/(\w+)/src/Filament/Widgets#', $path, $m);
            if (isset($m[1])) {
                $panel->discoverWidgets(
                    in: $path,
                    for: "App\\Modules\\{$m[1]}\\Filament\\Widgets",
                );
            }
        }

        return $panel;
    }
}
