<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BankAccountOverviewWidget;
use App\Filament\Widgets\FinanceStatsWidget;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;

class FinancePage extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calculator';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Finance Overview';

    protected static ?string $title = 'Finance';

    protected static ?int $navigationSort = 0;

    protected function getHeaderWidgets(): array
    {
        return [
            BankAccountOverviewWidget::class,
            FinanceStatsWidget::class,
        ];
    }
}
