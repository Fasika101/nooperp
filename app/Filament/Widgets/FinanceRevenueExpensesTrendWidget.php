<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ChecksShieldWidgetPermission;
use App\Filament\Widgets\Concerns\ScopesFinanceDataByBranch;
use App\Models\Expense;
use App\Models\Order;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class FinanceRevenueExpensesTrendWidget extends ChartWidget
{
    use ChecksShieldWidgetPermission;
    use HasWidgetShield;
    use ScopesFinanceDataByBranch;

    protected ?string $heading = 'Revenue vs expenses (last 30 days)';

    protected ?string $description = 'Sales revenue (excl. shipping) compared to total expenses by day';

    protected static ?int $sort = 21;

    protected int|string|array $columnSpan = 2;

    public static function canView(): bool
    {
        return static::hasWidgetPermission()
            && request()->routeIs('filament.admin.pages.finance-page');
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $startDate = now()->subDays(29)->startOfDay();
        $endDate = now()->endOfDay();

        $revenueQuery = Order::query()
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(created_at) as day'),
                DB::raw('SUM(total_amount - shipping_amount) as total')
            )
            ->groupBy('day')
            ->orderBy('day');

        $this->scopeOrders($revenueQuery);

        $revenueByDay = $revenueQuery->pluck('total', 'day')->all();

        $expenseQuery = Expense::query()
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->select(
                DB::raw('DATE(date) as day'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy('day')
            ->orderBy('day');

        $this->scopeExpenses($expenseQuery);

        $expenseByDay = $expenseQuery->pluck('total', 'day')->all();

        $labels = [];
        $revenue = [];
        $expenses = [];
        $current = Carbon::parse($startDate);
        while ($current <= $endDate) {
            $key = $current->format('Y-m-d');
            $labels[] = $current->format('M d');
            $revenue[] = round((float) ($revenueByDay[$key] ?? 0), 2);
            $expenses[] = round((float) ($expenseByDay[$key] ?? 0), 2);
            $current->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => __('Revenue'),
                    'data' => $revenue,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.08)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
                [
                    'label' => __('Expenses'),
                    'data' => $expenses,
                    'borderColor' => 'rgb(239, 68, 68)',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.08)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): ?array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
