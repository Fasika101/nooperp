<?php

namespace App\Filament\Resources;

use App\Filament\Exports\OrderItemExporter;
use App\Filament\Resources\OrderItemResource\Pages;
use App\Models\OrderItem;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OrderItemResource extends Resource
{
    protected static ?string $model = OrderItem::class;

    protected static ?string $navigationLabel = 'Sold Items';

    protected static ?string $modelLabel = 'Sold Item';

    protected static ?string $pluralModelLabel = 'Sold Items';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship('order', 'id')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => "Order #{$record->id} - {$record->customer?->name}"),
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if ($state) {
                            $product = \App\Models\Product::find($state);
                            if ($product) {
                                $set('price', $product->price);
                            }
                        }
                    }),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->default(1)
                    ->minValue(1),
                TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix($currency)
                    ->minValue(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.id')
                    ->label('Order')
                    ->formatStateUsing(fn ($state) => "#{$state}")
                    ->sortable(),
                Tables\Columns\TextColumn::make('order.customer.name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Item'),
                Tables\Columns\TextColumn::make('quantity'),
                Tables\Columns\TextColumn::make('price')
                    ->money($currency),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->label('Cost')
                    ->money($currency)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('cogs')
                    ->label('COGS')
                    ->getStateUsing(fn (OrderItem $record): float => $record->cogs)
                    ->money($currency)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('subtotal')
                    ->money($currency)
                    ->getStateUsing(fn (OrderItem $record): float => $record->quantity * $record->price),
                Tables\Columns\TextColumn::make('order.discount_amount')
                    ->label('Discount')
                    ->money($currency)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('revenue')
                    ->label('Revenue')
                    ->getStateUsing(fn (OrderItem $record): float => (float) $record->order->total_amount - (float) $record->order->shipping_amount)
                    ->money($currency),
                Tables\Columns\TextColumn::make('order.shipping_amount')
                    ->label('Shipping')
                    ->money($currency)
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Action::make('viewReceipt')
                    ->label('View Receipt')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (OrderItem $record): string => route('receipt.show', $record->order))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrderItems::route('/'),
            'create' => Pages\CreateOrderItem::route('/create'),
            'edit' => Pages\EditOrderItem::route('/{record}/edit'),
        ];
    }
}
