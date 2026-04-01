<?php

namespace App\Filament\Widgets;

use App\Models\BankAccount;
use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class BankAccountReconciliationWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 0;

    protected int | array | null $columns = 1;

    public static function canView(): bool
    {
        return static::hasWidgetPermission() && request()->routeIs('filament.admin.pages.dashboard');
    }

    protected static function hasWidgetPermission(): bool
    {
        $permission = static::getWidgetPermission();
        $user = \Filament\Facades\Filament::auth()?->user();

        return $permission && $user ? $user->can($permission) : true;
    }

    protected function getStats(): array
    {
        $currency = Setting::getDefaultCurrency();
        $bankBalance = BankAccount::getTotalBankBalance();

        return [
            Stat::make('Total Money In Accounts', Number::currency($bankBalance, $currency))
                ->description('Cash, bank, and mobile money balances added together')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('primary')
                ->icon('heroicon-o-credit-card'),
        ];
    }
}
