<?php

namespace App\Filament\Resources\AffiliateResource\RelationManagers;

use App\Filament\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Setting;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class AffiliatePayoutsRelationManager extends RelationManager
{
    protected static string $relationship = 'commissionPayouts';

    protected static ?string $title = 'Commission payouts (expenses)';

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
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('bankAccount.name')
                    ->label('Pay from account')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor')
                    ->label('Payee')
                    ->placeholder('—')
                    ->limit(30),
                Tables\Columns\TextColumn::make('description')
                    ->limit(30)
                    ->placeholder('—'),
            ])
            ->defaultSort('date', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Record payout')
                    ->icon('heroicon-o-banknotes')
                    ->url(function (): string {
                        $payoutTypeId = ExpenseType::affiliatePayoutTypeId();
                        if (! $payoutTypeId) {
                            return ExpenseResource::getUrl('create');
                        }
                        $params = [
                            'expense_type_id' => $payoutTypeId,
                            'affiliate_id' => $this->getOwnerRecord()->getKey(),
                        ];
                        $base = ExpenseResource::getUrl('create');

                        return $base.'?'.http_build_query($params);
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->url(fn (Expense $record): string => ExpenseResource::getUrl('view', ['record' => $record])),
                EditAction::make()
                    ->url(fn (Expense $record): string => ExpenseResource::getUrl('edit', ['record' => $record])),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ]);
    }
}
