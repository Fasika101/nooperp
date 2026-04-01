<?php

namespace App\Filament\Pages;

use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Livewire\Attributes\Url;

class ProfitLossReportPage extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'P&L Report';

    protected static ?string $title = 'Profit & Loss Report';

    protected static ?int $navigationSort = 1;

    #[Url(as: 'filters')]
    public ?array $tableFilters = null;

    public function mount(): void
    {
        $this->bootedInteractsWithTable();
        $this->mountInteractsWithTable();
        if ($this->tableFilters === null) {
            $this->tableFilters = ['period' => ['value' => 'this_month']];
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
        return $table
            ->heading('Profit & Loss Statement')
            ->description('Revenue, costs, and profit for the selected period.')
            ->records(fn (): array => $this->getPlRows())
            ->columns([
                TextColumn::make('label')
                    ->label('')
                    ->weight(fn ($record) => in_array($record['key'] ?? '', ['gross_profit', 'net_profit', 'subtotal', 'subtotal2']) ? 'bold' : null),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => $state !== null ? Number::currency($state, Setting::getDefaultCurrency()) : '—')
                    ->alignEnd(),
            ])
            ->filters([
                SelectFilter::make('period')
                    ->label('Period')
                    ->options([
                        'this_month' => 'This Month',
                        'last_month' => 'Last Month',
                        'this_quarter' => 'This Quarter',
                        'last_quarter' => 'Last Quarter',
                        'this_year' => 'This Year',
                    ])
                    ->default('this_month'),
            ])
            ->paginated(false)
            ->persistFiltersInSession()
            ->headerActions([
                Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => $this->exportCsv()),
            ]);
    }

    protected function getPlRows(): array
    {
        $filters = $this->tableFilters ?? [];
        $period = data_get($filters, 'period.value') ?? data_get($filters, 'period') ?? 'this_month';

        [$start, $end] = $this->getPeriodDates($period);

        $currency = Setting::getDefaultCurrency();

        $revenue = Order::where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->get()
            ->sum(fn ($o) => (float) $o->total_amount - (float) $o->shipping_amount);

        $cogs = (float) OrderItem::whereHas('order', fn ($q) => $q->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end]))
            ->get()
            ->sum(fn ($item) => (float) $item->quantity * (float) ($item->unit_cost ?? 0));

        $grossProfit = $revenue - $cogs;

        $inventoryPurchaseTypeId = ExpenseType::where('name', 'Inventory Purchase')->value('id');
        $operatingExpenses = Expense::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->when($inventoryPurchaseTypeId, fn ($q) => $q->where('expense_type_id', '!=', $inventoryPurchaseTypeId))
            ->sum('amount');

        $allExpenses = Expense::whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->sum('amount');

        $netProfit = $grossProfit - $operatingExpenses;

        return [
            ['__key' => 'revenue', 'key' => 'revenue', 'label' => 'Revenue', 'amount' => $revenue],
            ['__key' => 'cogs', 'key' => 'cogs', 'label' => 'Cost of Goods Sold (COGS)', 'amount' => -$cogs],
            ['__key' => 'gross_profit', 'key' => 'gross_profit', 'label' => 'Gross Profit', 'amount' => $grossProfit],
            ['__key' => 'subtotal', 'key' => 'subtotal', 'label' => '—', 'amount' => null],
            ['__key' => 'operating', 'key' => 'operating', 'label' => 'Operating Expenses', 'amount' => -$operatingExpenses],
            ['__key' => 'restock', 'key' => 'restock', 'label' => 'Inventory Purchase (Restock)', 'amount' => -($allExpenses - $operatingExpenses)],
            ['__key' => 'subtotal2', 'key' => 'subtotal2', 'label' => '—', 'amount' => null],
            ['__key' => 'net_profit', 'key' => 'net_profit', 'label' => 'Net Profit', 'amount' => $netProfit],
        ];
    }

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

    protected function exportCsv()
    {
        $rows = $this->getPlRows();
        $currency = Setting::getDefaultCurrency();

        $filters = $this->tableFilters ?? [];
        $period = data_get($filters, 'period.value') ?? data_get($filters, 'period') ?? 'this_month';
        $periodLabels = [
            'this_month' => 'This Month',
            'last_month' => 'Last Month',
            'this_quarter' => 'This Quarter',
            'last_quarter' => 'Last Quarter',
            'this_year' => 'This Year',
        ];
        $periodLabel = $periodLabels[$period] ?? $period;

        $csv = "Profit & Loss Report\n";
        $csv .= "Period: {$periodLabel}\n\n";
        $csv .= "Item,Amount\n";

        foreach ($rows as $row) {
            $amount = $row['amount'] !== null
                ? Number::currency($row['amount'], $currency)
                : '—';
            $csv .= '"'.str_replace('"', '""', $row['label']).'","'.$amount."\"\n";
        }

        $filename = 'profit-loss-report-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print ($csv),
            $filename,
            [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]
        );
    }
}
