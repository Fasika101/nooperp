<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

class LibaStatsOverviewWidget extends BaseWidget
{
    use HasWidgetShield;
    protected static ?int $sort = 1;

    protected int | array | null $columns = 4;

    protected function getStats(): array
    {
        $currency = Setting::getDefaultCurrency();
        $baseQuery = Order::where('status', 'completed');
        $totalRevenue = (clone $baseQuery)->get()->sum(fn ($o) => (float) $o->total_amount - (float) $o->shipping_amount);
        $revenueThisMonth = (clone $baseQuery)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->get()->sum(fn ($o) => (float) $o->total_amount - (float) $o->shipping_amount);
        $revenueLastMonth = (clone $baseQuery)->whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year)->get()->sum(fn ($o) => (float) $o->total_amount - (float) $o->shipping_amount);
        $revenueChange = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : ($revenueThisMonth > 0 ? 100 : 0);

        $totalShipping = (clone $baseQuery)->sum('shipping_amount');
        $shippingThisMonth = (clone $baseQuery)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->sum('shipping_amount');

        $ordersToday = Order::whereDate('created_at', today())->count();
        $ordersYesterday = Order::whereDate('created_at', today()->subDay())->count();
        $ordersThisMonth = Order::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $ordersLastMonth = Order::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        $ordersChange = $ordersLastMonth > 0
            ? round((($ordersThisMonth - $ordersLastMonth) / $ordersLastMonth) * 100, 1)
            : ($ordersThisMonth > 0 ? 100 : 0);

        $newCustomersThisMonth = Customer::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $newCustomersLastMonth = Customer::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        $customersChange = $newCustomersLastMonth > 0
            ? round((($newCustomersThisMonth - $newCustomersLastMonth) / $newCustomersLastMonth) * 100, 1)
            : ($newCustomersThisMonth > 0 ? 100 : 0);

        return [
            Stat::make('Total Revenue', Number::currency($totalRevenue, $currency))
                ->description($revenueChange >= 0 ? "{$revenueChange}% vs last month" : "{$revenueChange}% vs last month")
                ->descriptionIcon($revenueChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($revenueChange >= 0 ? 'success' : 'danger')
                ->color('success')
                ->icon('heroicon-o-currency-dollar'),
            Stat::make('Shipping Income', Number::currency($totalShipping, $currency))
                ->description("This month: " . Number::currency($shippingThisMonth, $currency))
                ->descriptionIcon('heroicon-m-truck')
                ->color('gray')
                ->icon('heroicon-o-truck'),
            Stat::make('New Sales Today', $ordersToday)
                ->description($ordersYesterday > 0 ? "Yesterday: {$ordersYesterday} orders" : 'No orders yesterday')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('primary')
                ->icon('heroicon-o-shopping-cart'),
            Stat::make('Sales This Month', $ordersThisMonth)
                ->description($ordersChange >= 0 ? "{$ordersChange}% vs last month" : "{$ordersChange}% vs last month")
                ->descriptionIcon($ordersChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($ordersChange >= 0 ? 'success' : 'danger')
                ->color('info')
                ->icon('heroicon-o-chart-bar'),
            Stat::make('Total Customers', Customer::count())
                ->description("+{$newCustomersThisMonth} new this month")
                ->descriptionIcon($customersChange >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($customersChange >= 0 ? 'success' : 'danger')
                ->color('warning')
                ->icon('heroicon-o-users'),
        ];
    }
}
