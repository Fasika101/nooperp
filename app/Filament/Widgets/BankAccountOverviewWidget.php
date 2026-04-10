<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ChecksShieldWidgetPermission;
use App\Models\BankAccount;
use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class BankAccountOverviewWidget extends BaseWidget
{
    use ChecksShieldWidgetPermission;
    use HasWidgetShield;

    protected static ?int $sort = 0;

    protected int|array|null $columns = 2;

    public static function canView(): bool
    {
        return static::hasWidgetPermission()
            && request()->routeIs('filament.admin.pages.finance-page');
    }

    protected function getStats(): array
    {
        $currency = Setting::getDefaultCurrency();
        $capital = BankAccount::getTotalCapital();
        $bankBalance = BankAccount::getTotalBankBalance();

        return [
            Stat::make('Total Capital', Number::currency($capital, $currency))
                ->description('Opening balance across all accounts')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success')
                ->icon('heroicon-o-building-library'),
            Stat::make('Total Money In Accounts', Number::currency($bankBalance, $currency))
                ->description('Cash, bank, and mobile money balances added together')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('primary')
                ->icon('heroicon-o-credit-card'),
        ];
    }
}
