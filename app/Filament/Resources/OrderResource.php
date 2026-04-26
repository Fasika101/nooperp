<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\PaymentsRelationManager;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-cart';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Section::make('Order')
                    ->schema([
                        Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('branch_id')
                            ->relationship('branch', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->required(),
                        Select::make('tax_type_id')
                            ->relationship('taxType', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('None'),
                    ])
                    ->columns(2),
                Section::make('Affiliate')
                    ->schema([
                        Select::make('affiliate_id')
                            ->label('Affiliate')
                            ->relationship('affiliate', 'name', fn ($query) => $query->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->placeholder('None')
                            ->disabled(),
                        Select::make('affiliate_commission_type')
                            ->label('Commission mode')
                            ->options([
                                Affiliate::COMMISSION_DEDUCT_PERCENT => 'Deduct % (customer pays pre-affiliate total)',
                                Affiliate::COMMISSION_ADD_PERCENT => 'Add % to sale (customer pays base + add-on)',
                            ])
                            ->disabled(),
                        TextInput::make('affiliate_commission_rate')
                            ->label('Rate (%)')
                            ->numeric()
                            ->suffix('%')
                            ->disabled(),
                        TextInput::make('affiliate_commission_amount')
                            ->label('Affiliate cut')
                            ->prefix($currency)
                            ->disabled(),
                    ])
                    ->columns(2)
                    ->visible(fn (?Order $record): bool => (bool) ($record?->affiliate_id))
                    ->collapsed(),
                Section::make('Amounts')
                    ->schema([
                        TextInput::make('total_amount')
                            ->label('Total')
                            ->required()
                            ->numeric()
                            ->prefix($currency)
                            ->default(0)
                            ->minValue(0),
                        TextInput::make('discount_amount')
                            ->label('Discount')
                            ->numeric()
                            ->prefix($currency)
                            ->default(0)
                            ->minValue(0),
                        Select::make('discount_type')
                            ->options([
                                'fixed' => 'Fixed',
                                'percentage' => 'Percentage',
                            ])
                            ->default('fixed')
                            ->required(),
                        TextInput::make('shipping_amount')
                            ->label('Shipping')
                            ->numeric()
                            ->prefix($currency)
                            ->default(0)
                            ->minValue(0),
                        TextInput::make('tax_amount')
                            ->label('Tax')
                            ->numeric()
                            ->prefix($currency)
                            ->default(0)
                            ->minValue(0),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('affiliate_commission_amount')
                    ->label('Aff. cut')
                    ->money($currency)
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->money($currency)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('balance_due')
                    ->label('Balance')
                    ->money($currency)
                    ->sortable()
                    ->color(fn ($state): string => (float) $state > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Pay status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        'unpaid' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'processing' => 'info',
                        'completed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
                Action::make('printReceipt')
                    ->label('Print receipt')
                    ->icon('heroicon-o-printer')
                    ->url(fn (Order $record): string => route('receipt.show', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            OrderItemsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->isBranchRestricted()) {
            $query->where('branch_id', $user->branch_id);
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
