<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Filament\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Setting;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    protected static ?string $title = 'Linked expenses (payroll)';

    public function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('expenseType.name')
                    ->label('Type')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('vendor')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->defaultSort('date', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('New salary expense')
                    ->icon('heroicon-o-plus')
                    ->url(function (): string {
                        $params = array_filter([
                            'expense_type_id' => ExpenseType::salariesTypeId(),
                            'employee_id' => $this->getOwnerRecord()->getKey(),
                        ]);
                        $base = ExpenseResource::getUrl('create');

                        return $params === [] ? $base : $base.'?'.http_build_query($params);
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (Expense $record): string => ExpenseResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->url(fn (Expense $record): string => ExpenseResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
