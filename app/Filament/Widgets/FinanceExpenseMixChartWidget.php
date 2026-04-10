<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\ChecksShieldWidgetPermission;
use App\Filament\Widgets\Concerns\ScopesFinanceDataByBranch;
use App\Models\Expense;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class FinanceExpenseMixChartWidget extends ChartWidget
{
    use ChecksShieldWidgetPermission;
    use HasWidgetShield;
    use ScopesFinanceDataByBranch;

    protected ?string $heading = 'Expenses by type (this month)';

    protected ?string $description = 'Share of recorded expenses in the current calendar month';

    protected static ?int $sort = 20;

    protected int|string|array $columnSpan = 2;

    public static function canView(): bool
    {
        return static::hasWidgetPermission()
            && request()->routeIs('filament.admin.pages.finance-page');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $query = Expense::query()
            ->select(['expense_types.name', DB::raw('SUM(expenses.amount) as total')])
            ->join('expense_types', 'expenses.expense_type_id', '=', 'expense_types.id')
            ->whereBetween('expenses.date', [$start->toDateString(), $end->toDateString()])
            ->groupBy('expense_types.name')
            ->orderByDesc('total');

        $this->scopeExpenses($query);

        $rows = $query->get();

        $labels = $rows->pluck('name')->all();
        $values = $rows->pluck('total')->map(fn ($v) => round((float) $v, 2))->all();

        if ($labels === []) {
            $labels = [__('No expenses')];
            $values = [0];
        }

        $colors = [
            'rgba(59, 130, 246, 0.85)',
            'rgba(16, 185, 129, 0.85)',
            'rgba(245, 158, 11, 0.85)',
            'rgba(239, 68, 68, 0.85)',
            'rgba(139, 92, 246, 0.85)',
            'rgba(236, 72, 153, 0.85)',
            'rgba(14, 165, 233, 0.85)',
            'rgba(100, 116, 139, 0.85)',
        ];

        $backgroundColor = [];
        for ($i = 0; $i < count($labels); $i++) {
            $backgroundColor[] = $colors[$i % count($colors)];
        }

        return [
            'datasets' => [
                [
                    'label' => __('Amount'),
                    'data' => $values,
                    'backgroundColor' => $backgroundColor,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): ?array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'right',
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
