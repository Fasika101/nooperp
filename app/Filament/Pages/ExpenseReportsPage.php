<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseReportsPage extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-magnifying-glass';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Expense reports';

    protected static ?string $title = 'Expense reports';

    protected static ?int $navigationSort = 2;

    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    public function mount(): void
    {
        $this->bootedInteractsWithTable();
        $this->mountInteractsWithTable();
        if ($this->tableFilters === null) {
            $this->tableFilters = [
                'period' => ['value' => 'this_month'],
                'scope' => ['value' => 'all'],
            ];
        }
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->heading('Filtered expenses')
            ->description('Pick an expense type (optional), a report type, then either a preset period or a custom date range. When both From and Until are set, the custom range is used instead of the period.')
            ->query(function (): Builder {
                $query = Expense::query()->with(['expenseType', 'bankAccount', 'branch']);
                if (auth()->user()?->isBranchRestricted()) {
                    $query->where('branch_id', auth()->user()->branch_id);
                }

                return $query;
            })
            ->columns([
                TextColumn::make('date')
                    ->date()
                    ->sortable(),
                TextColumn::make('amount')
                    ->money($currency)
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('bankAccount.name')
                    ->label('Account')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('expenseType.name')
                    ->label('Type')
                    ->badge()
                    ->placeholder('—'),
                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('vendor')
                    ->placeholder('—'),
                TextColumn::make('description')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('expense_type_id')
                    ->label('Expense type')
                    ->relationship(
                        'expenseType',
                        'name',
                        fn ($query) => $query->where('is_active', true)->orderBy('name'),
                    )
                    ->searchable()
                    ->preload()
                    ->placeholder('All types')
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return filled($value)
                            ? $query->where($this->qualifyExpenseColumn('expense_type_id'), $value)
                            : $query;
                    }),
                SelectFilter::make('scope')
                    ->label('Report type')
                    ->options([
                        'all' => 'All expenses',
                        'salaries' => 'Salaries only',
                        'inventory' => 'Inventory / product purchases',
                        'operating' => 'Operating (excl. inventory purchases)',
                    ])
                    ->default('all')
                    ->query(fn (Builder $query, array $data): Builder => $this->applyScopeToExpenseQuery(
                        $query,
                        (string) ($data['value'] ?? 'all'),
                    )),
                SelectFilter::make('period')
                    ->label('Period')
                    ->options([
                        'this_month' => 'This month',
                        'last_month' => 'Last month',
                        'this_quarter' => 'This quarter',
                        'last_quarter' => 'Last quarter',
                        'this_year' => 'This year',
                    ])
                    ->default('this_month')
                    ->query(function (Builder $query, array $data): Builder {
                        if ($this->hasActiveCustomDateRange()) {
                            return $query;
                        }

                        return $this->applyPeriodToExpenseQuery(
                            $query,
                            (string) ($data['value'] ?? 'this_month'),
                        );
                    }),
                Filter::make('date_range')
                    ->label('Custom date range')
                    ->schema([
                        DatePicker::make('from')
                            ->label('From')
                            ->native(false),
                        DatePicker::make('until')
                            ->label('Until')
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = $data['from'] ?? null;
                        $until = $data['until'] ?? null;
                        if (! filled($from) || ! filled($until)) {
                            return $query;
                        }

                        return $query->whereBetween($this->qualifyExpenseColumn('date'), [
                            Carbon::parse($from)->format('Y-m-d'),
                            Carbon::parse($until)->format('Y-m-d'),
                        ]);
                    })
                    ->indicateUsing(function (array $state): array {
                        $from = $state['from'] ?? null;
                        $until = $state['until'] ?? null;
                        if (! filled($from) || ! filled($until)) {
                            return [];
                        }

                        return [
                            Indicator::make(
                                Carbon::parse($from)->toFormattedDateString()
                                .' – '
                                .Carbon::parse($until)->toFormattedDateString(),
                            ),
                        ];
                    }),
            ])
            ->persistFiltersInSession()
            ->headerActions([
                Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => $this->exportCsv()),
            ])
            ->actions([
                Action::make('open')
                    ->label('Edit')
                    ->icon('heroicon-m-pencil-square')
                    ->url(fn (Expense $record): string => ExpenseResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    /**
     * Filament may pass a base query builder without an Eloquent model; qualify via {@see Expense}.
     */
    protected function qualifyExpenseColumn(string $column): string
    {
        return (new Expense)->qualifyColumn($column);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    protected function applyAllReportFilters(Builder $query, array $filters): Builder
    {
        $from = data_get($filters, 'date_range.from');
        $until = data_get($filters, 'date_range.until');

        if (filled($from) && filled($until)) {
            $query->whereBetween($this->qualifyExpenseColumn('date'), [
                Carbon::parse($from)->format('Y-m-d'),
                Carbon::parse($until)->format('Y-m-d'),
            ]);
        } else {
            $period = (string) data_get($filters, 'period.value', data_get($filters, 'period', 'this_month'));
            $this->applyPeriodToExpenseQuery($query, $period);
        }

        $typeId = data_get($filters, 'expense_type_id.value');
        if (filled($typeId)) {
            $query->where($this->qualifyExpenseColumn('expense_type_id'), $typeId);
        }

        $scope = (string) data_get($filters, 'scope.value', data_get($filters, 'scope', 'all'));
        $this->applyScopeToExpenseQuery($query, $scope);

        return $query;
    }

    protected function hasActiveCustomDateRange(): bool
    {
        $filters = $this->tableFilters ?? [];
        $from = data_get($filters, 'date_range.from');
        $until = data_get($filters, 'date_range.until');

        return filled($from) && filled($until);
    }

    protected function applyPeriodToExpenseQuery(Builder $query, string $period): Builder
    {
        [$start, $end] = $this->getPeriodDates($period);

        return $query->whereBetween(
            $this->qualifyExpenseColumn('date'),
            [$start->format('Y-m-d'), $end->format('Y-m-d')],
        );
    }

    protected function applyScopeToExpenseQuery(Builder $query, string $scope): Builder
    {
        $inventoryId = ExpenseType::where('name', 'Inventory Purchase')->value('id');
        $salariesId = ExpenseType::salariesTypeId();

        return match ($scope) {
            'salaries' => $salariesId
                ? $query->where($this->qualifyExpenseColumn('expense_type_id'), $salariesId)
                : $query->whereRaw('0 = 1'),
            'inventory' => $inventoryId
                ? $query->where($this->qualifyExpenseColumn('expense_type_id'), $inventoryId)
                : $query->whereRaw('0 = 1'),
            'operating' => $inventoryId
                ? $query->where($this->qualifyExpenseColumn('expense_type_id'), '!=', $inventoryId)
                : $query,
            default => $query,
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function getPeriodDates(string $period): array
    {
        $now = now();

        return match ($period) {
            'last_month' => [
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            'this_quarter' => [
                $now->copy()->startOfQuarter(),
                $now->copy()->endOfQuarter(),
            ],
            'last_quarter' => [
                $now->copy()->subQuarter()->startOfQuarter(),
                $now->copy()->subQuarter()->endOfQuarter(),
            ],
            'this_year' => [
                $now->copy()->startOfYear(),
                $now->copy()->endOfYear(),
            ],
            default => [
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
        };
    }

    protected function exportCsv(): StreamedResponse
    {
        $currency = Setting::getDefaultCurrency();
        $filters = $this->tableFilters ?? [];

        $period = (string) data_get($filters, 'period.value', data_get($filters, 'period', 'this_month'));
        $scope = (string) data_get($filters, 'scope.value', data_get($filters, 'scope', 'all'));

        $periodLabels = [
            'this_month' => 'This month',
            'last_month' => 'Last month',
            'this_quarter' => 'This quarter',
            'last_quarter' => 'Last quarter',
            'this_year' => 'This year',
        ];
        $scopeLabels = [
            'all' => 'All expenses',
            'salaries' => 'Salaries only',
            'inventory' => 'Inventory / product purchases',
            'operating' => 'Operating (excl. inventory purchases)',
        ];

        $query = Expense::query()->with(['expenseType', 'bankAccount', 'branch']);
        if (auth()->user()?->isBranchRestricted()) {
            $query->where('branch_id', auth()->user()->branch_id);
        }
        $this->applyAllReportFilters($query, $filters);

        $total = (float) $query->clone()->sum('amount');
        $rows = $query->orderBy('date', 'desc')->get();

        $from = data_get($filters, 'date_range.from');
        $until = data_get($filters, 'date_range.until');
        $dateLine = (filled($from) && filled($until))
            ? 'Dates: '.Carbon::parse($from)->toFormattedDateString().' – '.Carbon::parse($until)->toFormattedDateString()
            : 'Period: '.($periodLabels[$period] ?? $period);

        $typeId = data_get($filters, 'expense_type_id.value');
        $typeLine = filled($typeId)
            ? 'Expense type: '.ExpenseType::whereKey($typeId)->value('name')
            : 'Expense type: All types';

        $csv = "Expense report\n";
        $csv .= $dateLine."\n";
        $csv .= $typeLine."\n";
        $csv .= 'Report type: '.($scopeLabels[$scope] ?? $scope)."\n";
        $csv .= 'Total: '.Number::currency($total, $currency)."\n\n";
        $csv .= "Date,Amount,Branch,Account,Type,Vendor,Description\n";

        foreach ($rows as $row) {
            $csv .= '"'.$row->date?->format('Y-m-d').'",';
            $csv .= '"'.$row->amount.'",';
            $csv .= '"'.str_replace('"', '""', (string) ($row->branch?->name ?? '')).'",';
            $csv .= '"'.str_replace('"', '""', (string) ($row->bankAccount?->name ?? '')).'",';
            $csv .= '"'.str_replace('"', '""', (string) ($row->expenseType?->name ?? '')).'",';
            $csv .= '"'.str_replace('"', '""', (string) ($row->vendor ?? '')).'",';
            $csv .= '"'.str_replace(["\r", "\n", '"'], [' ', ' ', '""'], (string) ($row->description ?? ''))."\"\n";
        }

        $filename = 'expense-report-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(
            function () use ($csv): void {
                echo $csv;
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ],
        );
    }
}
