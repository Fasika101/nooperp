<?php

namespace App\Filament\Resources\AffiliateResource\RelationManagers;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Setting;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AffiliateOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Earning history (orders)';

    public function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->where('status', 'completed')
                ->withSum('affiliateCommissionSettlements as commission_settled_sum', 'amount')
                ->orderByDesc('created_at'))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Order #')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Order total')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('affiliate_commission_amount')
                    ->label('Affiliate cut')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_settled_sum')
                    ->label('Settled')
                    ->money($currency)
                    ->placeholder('0')
                    ->sortable(),
                Tables\Columns\TextColumn::make('commission_remaining')
                    ->label('Remaining')
                    ->money($currency)
                    ->state(function (Order $record): float {
                        $settled = (float) ($record->commission_settled_sum ?? 0);

                        return round(max(0, (float) $record->affiliate_commission_amount - $settled), 2);
                    })
                    ->color('warning'),
                Tables\Columns\TextColumn::make('affiliate_commission_type')
                    ->label('Mode')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'add_percent' => 'Add %',
                        'deduct_percent' => 'Deduct %',
                        default => '—',
                    })
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('affiliate_commission_rate')
                    ->label('Rate %')
                    ->suffix('%')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
