<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ProductDeletionLogResource\Pages;
use App\Models\ProductDeletionLog;
use App\Models\Setting;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductDeletionLogResource extends Resource
{
    protected static ?string $model = ProductDeletionLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box-x-mark';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Deleted Products Log';

    protected static ?string $modelLabel = 'Deletion Log';

    protected static ?string $pluralModelLabel = 'Deleted Products Log';

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

    public static function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('remaining_stock')
                    ->label('Stock at deletion')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('product_cost_price')
                    ->label('Cost price')
                    ->money($currency)
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('refunded_amount')
                    ->label('Refunded to account')
                    ->money($currency)
                    ->sortable()
                    ->color(fn (float $state): string => $state > 0 ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('bankAccount.name')
                    ->label('Bank account')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('deletedBy.name')
                    ->label('Deleted by')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Reason / Notes')
                    ->placeholder('—')
                    ->limit(60)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Deleted at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductDeletionLogs::route('/'),
        ];
    }
}
