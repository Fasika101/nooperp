<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SalesChartWidget extends ChartWidget
{
    use HasWidgetShield;
    protected ?string $heading = 'Orders Over Time';

    protected ?string $description = 'Number of orders for the last 7 days';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 2;

    public ?string $filter = 'week';

    protected function getType(): string
    {
        return 'bar';
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

        $query = Order::whereBetween('created_at', [$startDate, $endDate]);

        if ($groupBy === 'day') {
            $data = $query->select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('COUNT(*) as total')
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
                $values[] = $data[$dateStr] ?? 0;
                $current->addDay();
            }
        } else {
            $data = $query->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('COUNT(*) as total')
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
                $match = $data->first(fn ($d) => (int) $d->year === (int) $current->year && (int) $d->month === (int) $current->month);
                $values[] = $match ? (int) $match->total : 0;
                $current->addMonth();
            }
        }

        return [
            'datasets' => [
                [
                    'label' => 'Orders',
                    'data' => $values,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.6)',
                    'borderColor' => 'rgb(34, 197, 94)',
                    'borderWidth' => 1,
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
