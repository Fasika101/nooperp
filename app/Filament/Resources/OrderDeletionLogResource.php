<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\OrderDeletionLogResource\Pages;
use App\Models\OrderDeletionLog;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OrderDeletionLogResource extends Resource
{
    protected static ?string $model = OrderDeletionLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-x-mark';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?string $navigationLabel = 'Deleted Orders Log';

    protected static ?string $modelLabel = 'Deletion Log';

    protected static ?string $pluralModelLabel = 'Deleted Orders Log';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        return $user?->hasRole(User::ROLE_SUPER_ADMIN) || $user?->hasRole(User::ROLE_MANAGER);
    }

    public static function infolist(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->columns(1)
            ->components([
                Section::make('Order snapshot')
                    ->schema([
                        TextEntry::make('original_order_id')->label('Order #'),
                        TextEntry::make('customer_name')->label('Customer')->placeholder('—'),
                        TextEntry::make('branch_name')->label('Branch')->placeholder('—'),
                        TextEntry::make('order_status')->label('Status')->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'completed' => 'success',
                                'pending' => 'warning',
                                'cancelled' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('total_amount')->label('Total')->money($currency),
                        TextEntry::make('amount_paid')->label('Paid')->money($currency),
                        TextEntry::make('payment_status')->label('Payment status')->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'paid' => 'success',
                                'partial' => 'warning',
                                'unpaid' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('affiliate_name')->label('Affiliate')->placeholder('—'),
                        TextEntry::make('affiliate_commission_amount')->label('Affiliate commission')->money($currency)->placeholder('—'),
                    ])
                    ->columns(3),
                Section::make('Line items')
                    ->schema([
                        RepeatableEntry::make('items_snapshot')
                            ->label('')
                            ->schema([
                                TextEntry::make('name')->label('Product'),
                                TextEntry::make('quantity')->label('Qty'),
                                TextEntry::make('price')->label('Price')->money($currency),
                                TextEntry::make('unit_cost')->label('Unit cost')->money($currency)->placeholder('—'),
                            ])
                            ->columns(4),
                    ]),
                Section::make('Deletion info')
                    ->schema([
                        TextEntry::make('deletedBy.name')->label('Deleted by')->placeholder('—'),
                        TextEntry::make('created_at')->label('Deleted at')->dateTime(),
                        TextEntry::make('notes')->label('Reason / Notes')->placeholder('—')->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('original_order_id')
                    ->label('Order #')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('branch_name')
                    ->label('Branch')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->money($currency)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Pay status')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        'unpaid' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('affiliate_name')
                    ->label('Affiliate')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('affiliate_commission_amount')
                    ->label('Aff. commission')
                    ->money($currency)
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('deletedBy.name')
                    ->label('Deleted by')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Reason')
                    ->placeholder('—')
                    ->limit(50)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Deleted at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderDeletionLogs::route('/'),
            'view' => Pages\ViewOrderDeletionLog::route('/{record}'),
        ];
    }
}
