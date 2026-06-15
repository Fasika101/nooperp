<?php

namespace App\Providers\Filament;

use App\Models\ProjectTask;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ProjectsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('projects')
            ->path('projects')
            ->viteTheme('resources/css/filament/projects/theme.css')
            ->brandName('Liba Projects')
            ->brandLogo(null)
            ->login()
            ->navigationGroups([
                NavigationGroup::make()->label('My Work'),
                NavigationGroup::make()->label('Management'),
                NavigationGroup::make()->label('Settings'),
            ])
            ->colors([
                'primary' => Color::Violet,
            ])
            ->discoverResources(in: app_path('Filament/Projects/Resources'), for: 'App\\Filament\\Projects\\Resources')
            ->discoverPages(in: app_path('Filament/Projects/Pages'), for: 'App\\Filament\\Projects\\Pages')
            ->discoverWidgets(in: app_path('Filament/Projects/Widgets'), for: 'App\\Filament\\Projects\\Widgets')
            ->navigationItems([
                NavigationItem::make('Tasks Due Today')
                    ->url('/projects/project-tasks?tableFilters[due_today]=1')
                    ->icon('heroicon-o-exclamation-circle')
                    ->group('My Work')
                    ->sort(15)
                    ->badge(fn (): ?string => (string) (ProjectTask::query()
                        ->whereHas('project', function ($q) {
                            $uid = auth()->id();
                            $q->where('created_by', $uid)
                                ->orWhereHas('members', fn ($m) => $m->whereKey($uid));
                        })
                        ->whereDate('due_date', today())
                        ->count() ?: null)),
            ])
            ->widgets([])
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
            ->databaseNotifications()
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn (): string => '<meta name="viewport" content="width=device-width, initial-scale=1.0, minimum-scale=1.0, maximum-scale=1.0, user-scalable=0, user-scalable=no">'
                    . '<script>document.addEventListener("touchstart",function(e){if(e.touches.length>1){e.preventDefault();}},{passive:false});var lastTouch=0;document.addEventListener("touchend",function(e){var now=Date.now();if(now-lastTouch<=300){e.preventDefault();}lastTouch=now;},{passive:false});</script>',
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn (): string => view('filament.projects.hooks.return-to-erp')->render(),
            );
    }
}
