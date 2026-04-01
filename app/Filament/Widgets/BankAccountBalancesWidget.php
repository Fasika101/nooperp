<?php

namespace App\Filament\Widgets;

use App\Models\BankAccount;
use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class BankAccountBalancesWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return max(1, min(BankAccount::query()->count(), 4));
    }

    protected function getStats(): array
    {
        $currency = Setting::getDefaultCurrency();

        return BankAccount::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(function (BankAccount $account) use ($currency): Stat {
                $descriptionParts = collect([
                    $account->bank_name,
                    $account->is_default ? 'Default account' : null,
                ])->filter()->implode(' • ');

                return Stat::make($account->name, Number::currency((float) $account->current_balance, $currency))
                    ->description($descriptionParts !== '' ? $descriptionParts : 'Tracked account balance')
                    ->descriptionIcon($account->is_default ? 'heroicon-m-star' : 'heroicon-m-banknotes')
                    ->color($account->is_default ? 'success' : 'primary')
                    ->icon('heroicon-o-building-library');
            })
            ->all();
    }
}
