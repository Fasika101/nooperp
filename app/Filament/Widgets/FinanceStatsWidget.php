<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ChecksShieldWidgetPermission;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class FinanceStatsWidget extends BaseWidget
{
    use ChecksShieldWidgetPermission;
    use HasWidgetShield;

    protected static ?int $sort = 1;

    protected int|array|null $columns = 4;

    public static function canView(): bool
    {
        return static::hasWidgetPermission()
            && request()->routeIs('filament.admin.pages.finance-page');
    }

    protected function getStats(): array
    {
        $currency = Setting::getDefaultCurrency();
        $now = now();
        $lastMonth = $now->copy()->subMonth();

        $revenueThisMonth = Order::where('status', 'completed')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->get()
            ->sum(fn ($o) => (float) $o->total_amount - (float) $o->shipping_amount);
        $revenueLastMonth = Order::where('status', 'completed')
            ->whereMonth('created_at', $lastMonth->month)
            ->whereYear('created_at', $lastMonth->year)
            ->get()
            ->sum(fn ($o) => (float) $o->total_amount - (float) $o->shipping_amount);
        $revenueChange = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : ($revenueThisMonth > 0 ? 100 : 0);

        $cogsThisMonth = $this->getCogsForPeriod($now->month, $now->year);
        $cogsLastMonth = $this->getCogsForPeriod($lastMonth->month, $lastMonth->year);
        $grossProfitThisMonth = $revenueThisMonth - $cogsThisMonth;
        $grossProfitLastMonth = $revenueLastMonth - $cogsLastMonth;
        $grossProfitChange = $grossProfitLastMonth != 0
            ? round((($grossProfitThisMonth - $grossProfitLastMonth) / abs($grossProfitLastMonth)) * 100, 1)
            : ($grossProfitThisMonth > 0 ? 100 : 0);

        $inventoryPurchaseTypeId = ExpenseType::where('name', 'Inventory Purchase')->value('id');
        $operatingExpensesThisMonth = Expense::whereMonth('date', $now->month)
            ->whereYear('date', $now->year)
            ->when($inventoryPurchaseTypeId, fn ($q) => $q->where('expense_type_id', '!=', $inventoryPurchaseTypeId))
            ->sum('amount');
        $operatingExpensesLastMonth = Expense::whereMonth('date', $lastMonth->month)
            ->whereYear('date', $lastMonth->year)
            ->when($inventoryPurchaseTypeId, fn ($q) => $q->where('expense_type_id', '!=', $inventoryPurchaseTypeId))
            ->sum('amount');
        $expensesChange = $operatingExpensesLastMonth > 0
            ? round((($operatingExpensesThisMonth - $operatingExpensesLastMonth) / $operatingExpensesLastMonth) * 100, 1)
            : ($operatingExpensesThisMonth > 0 ? 100 : 0);

        $allExpensesThisMonth = Expense::whereMonth('date', $now->month)
            ->whereYear('date', $now->year)
            ->sum('amount');
        $allExpensesLastMonth = Expense::whereMonth('date', $lastMonth->month)
            ->whereYear('date', $lastMonth->year)
            ->sum('amount');
        $allExpensesChange = $allExpensesLastMonth > 0
            ? round((($allExpensesThisMonth - $allExpensesLastMonth) / $allExpensesLastMonth) * 100, 1)
            : ($allExpensesThisMonth > 0 ? 100 : 0);

        $netProfitThisMonth = $grossProfitThisMonth - $operatingExpensesThisMonth;
        $netProfitLastMonth = $grossProfitLastMonth - $operatingExpensesLastMonth;
        $profitChange = $netProfitLastMonth != 0
            ? round((($netProfitThisMonth - $netProfitLastMonth) / abs($netProfitLastMonth)) * 100, 1)
            : ($netProfitThisMonth > 0 ? 100 : 0);

        $shippingThisMonth = Order::where('status', 'completed')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('shipping_amount');

        $salariesTypeId = ExpenseType::salariesTypeId();
        $inventoryPurchasesThisMonth = $inventoryPurchaseTypeId
            ? (float) Expense::where('expense_type_id', $inventoryPurchaseTypeId)
                ->whereMonth('date', $now->month)
                ->whereYear('date', $now->year)
                ->sum('amount')
            : 0.0;
        $salariesThisMonth = $salariesTypeId
            ? (float) Expense::where('expense_type_id', $salariesTypeId)
                ->whereMonth('date', $now->month)
                ->whereYear('date', $now->year)
                ->sum('amount')
            : 0.0;

        return [
            Stat::make('Revenue This Month', Number::currency($revenueThisMonth, $currency))
                ->description($revenueChange >= 0 ? "+{$revenueChange}% vs last month" : "{$revenueChange}% vs last month")
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($revenueChange >= 0 ? 'success' : 'danger')
                ->color('success')
                ->icon('heroicon-o-currency-dollar'),
            Stat::make('Restocks This Month', Number::currency($cogsThisMonth, $currency))
                ->description('Cost of goods sold')
                ->descriptionIcon('heroicon-m-cube')
                ->color('gray')
                ->icon('heroicon-o-cube'),
            Stat::make('Gross Profit', Number::currency($grossProfitThisMonth, $currency))
                ->description($grossProfitChange >= 0 ? "+{$grossProfitChange}% vs last month" : "{$grossProfitChange}% vs last month")
                ->descriptionIcon($grossProfitChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($grossProfitChange >= 0 ? 'success' : 'danger')
                ->color('success')
                ->icon('heroicon-o-chart-bar'),
            Stat::make('Shipping This Month', Number::currency($shippingThisMonth, $currency))
                ->description('Delivery income')
                ->descriptionIcon('heroicon-m-truck')
                ->color('gray')
                ->icon('heroicon-o-truck'),
            Stat::make('Inventory purchases (cash)', Number::currency($inventoryPurchasesThisMonth, $currency))
                ->description('Expense type: Inventory Purchase')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('gray')
                ->icon('heroicon-o-shopping-cart'),
            Stat::make('Payroll this month', Number::currency($salariesThisMonth, $currency))
                ->description('Expense type: Salaries')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray')
                ->icon('heroicon-o-users'),
            Stat::make('Total Expenses This Month', Number::currency($allExpensesThisMonth, $currency))
                ->description('Rent, restock, utilities, etc. '.($allExpensesChange >= 0 ? "+{$allExpensesChange}% vs last month" : "{$allExpensesChange}% vs last month"))
                ->descriptionIcon($allExpensesChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($allExpensesChange >= 0 ? 'danger' : 'success')
                ->color('danger')
                ->icon('heroicon-o-banknotes'),
            Stat::make('Net Profit This Month', Number::currency($netProfitThisMonth, $currency))
                ->description($profitChange >= 0 ? "+{$profitChange}% vs last month" : "{$profitChange}% vs last month")
                ->descriptionIcon($profitChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($profitChange >= 0 ? 'success' : 'danger')
                ->color($netProfitThisMonth >= 0 ? 'success' : 'danger')
                ->icon('heroicon-o-chart-bar'),
        ];
    }

    protected function getCogsForPeriod(int $month, int $year): float
    {
        return (float) OrderItem::whereHas('order', fn ($q) => $q->where('status', 'completed')
            ->whereMonth('created_at', $month)
            ->whereYear('created_at', $year))
            ->get()
            ->sum(fn ($item) => (float) $item->quantity * (float) ($item->unit_cost ?? 0));
    }
}
