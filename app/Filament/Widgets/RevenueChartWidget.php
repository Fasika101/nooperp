<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class RevenueChartWidget extends ChartWidget
{
    use HasWidgetShield;
    protected ?string $heading = 'Revenue Over Time';

    protected ?string $description = 'Product sales revenue (excl. shipping) for the last 7 days';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 2;

    public ?string $filter = 'week';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $filter = $this->filter ?? 'week';

        if ($filter === 'month') {
            $startDate = now()->subMonths(11)->startOfMonth();
            $endDate = now()->endOfMonth();
            $groupBy = 'month';
        } else {
            $startDate = now()->subDays(6)->startOfDay();
            $endDate = now()->endOfDay();
            $groupBy = 'day';
        }

        if ($groupBy === 'day') {
            $rawData = Order::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('SUM(total_amount - shipping_amount) as total')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->pluck('total', 'date')
                ->toArray();

            $labels = [];
            $values = [];
            $current = Carbon::parse($startDate);
            while ($current <= $endDate) {
                $dateStr = $current->format('Y-m-d');
                $labels[] = $current->format('M d');
                $values[] = $rawData[$dateStr] ?? 0;
                $current->addDay();
            }
        } else {
            $rawData = Order::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('SUM(total_amount - shipping_amount) as total')
                )
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get();

            $labels = [];
            $values = [];
            $current = Carbon::parse($startDate);
            while ($current <= $endDate) {
                $labels[] = $current->format('M Y');
                $match = $rawData->first(fn ($d) => (int) $d->year === (int) $current->year && (int) $d->month === (int) $current->month);
                $values[] = $match ? (float) $match->total : 0;
                $current->addMonth();
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $values,
                    'fill' => true,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'tension' => 0.3,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getFilters(): ?array
    {
        return [
            'week' => 'Last 7 days',
            'month' => 'Last 12 months',
        ];
    }
}
