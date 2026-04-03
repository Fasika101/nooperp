<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Filament\Pages\DataWipePage;
use App\Filament\Pages\EditProfilePage;
use App\Filament\Pages\FinancePage;
use App\Filament\Pages\IntegrationsPage;
use App\Filament\Pages\LandingPageSettingsPage;
use App\Filament\Pages\MyProjectsPage;
use App\Filament\Pages\MyTasksPage;
use App\Filament\Pages\PosPage;
use App\Filament\Pages\ProfitLossReportPage;
use App\Filament\Pages\SettingsPage;
use App\Filament\Pages\TelegramCrmReportPage;
use App\Filament\Widgets\LibaStatsOverviewWidget;
use App\Filament\Widgets\RevenueChartWidget;
use App\Filament\Widgets\SalesChartWidget;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
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
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->path('admin')
            ->brandName('Liba ERP')
            ->login()
            ->profile(EditProfilePage::class, isSimple: false)
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('Sales'),
                NavigationGroup::make()
                    ->label('Inventory'),
                NavigationGroup::make()
                    ->label('Optical'),
                NavigationGroup::make()
                    ->label('Finance'),
                NavigationGroup::make()
                    ->label('CRM'),
                NavigationGroup::make()
                    ->label('Projects'),
                NavigationGroup::make()
                    ->label('Settings'),
                NavigationGroup::make()
                    ->label('Filament Shield'),
            ])
            ->colors([
                'primary' => Color::Blue,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
                PosPage::class,
                FinancePage::class,
                ProfitLossReportPage::class,
                SettingsPage::class,
                LandingPageSettingsPage::class,
                IntegrationsPage::class,
                DataWipePage::class,
                TelegramCrmReportPage::class,
                MyProjectsPage::class,
                MyTasksPage::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                LibaStatsOverviewWidget::class,
                RevenueChartWidget::class,
                SalesChartWidget::class,
                AccountWidget::class,
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
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.hooks.telegram-unread-poller')->render(),
            );
    }
}
