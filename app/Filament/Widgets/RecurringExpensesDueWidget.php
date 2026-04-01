<?php

namespace App\Filament\Widgets;

use App\Models\ExpenseType;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecurringExpensesDueWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Recurring expenses due this period')
            ->description('These expense types are set as recurring but have no expense recorded for the current period. Add an expense to clear them.')
            ->records(function (): array {
                $now = now();
                $due = [];
                foreach (ExpenseType::recurring()->get() as $type) {
                    $hasExpense = match ($type->frequency) {
                        'weekly' => $type->expenses()->whereBetween('date', [
                            $now->copy()->startOfWeek()->toDateString(),
                            $now->copy()->endOfWeek()->toDateString(),
                        ])->exists(),
                        'monthly' => $type->expenses()->whereMonth('date', $now->month)->whereYear('date', $now->year)->exists(),
                        'yearly' => $type->expenses()->whereYear('date', $now->year)->exists(),
                        default => true,
                    };
                    if (! $hasExpense) {
                        $due[$type->id] = [
                            'id' => $type->id,
                            'name' => $type->name,
                            'frequency' => $type->frequency,
                            'day_of_month' => $type->day_of_month,
                        ];
                    }
                }
                return $due;
            })
            ->columns([
                TextColumn::make('name')
                    ->label('Expense type'),
                TextColumn::make('frequency')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—'),
                TextColumn::make('day_of_month')
                    ->label('Due day')
                    ->formatStateUsing(fn ($state) => $state ? "Day {$state}" : '—'),
            ])
            ->actions([
                Action::make('add_expense')
                    ->label('Add expense')
                    ->url(fn (array $record) => \App\Filament\Resources\ExpenseResource::getUrl('create') . '?expense_type_id=' . $record['id'])
                    ->icon('heroicon-o-plus'),
            ])
            ->emptyStateHeading('All recurring expenses recorded')
            ->emptyStateDescription('Every recurring expense type has an entry for the current period.');
    }
}
