<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\RelationManagers\OrderItemsRelationManager;
use App\Filament\Resources\OrderResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Resources\WebsiteOrderResource\Pages;
use App\Filament\Resources\WebsiteOrderResource\RelationManagers\ShopPrescriptionsRelationManager;
use App\Models\Order;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WebsiteOrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'website-orders';

    protected static ?string $navigationLabel = 'Website orders';

    protected static ?string $modelLabel = 'website order';

    protected static ?string $pluralModelLabel = 'Website orders';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Website';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('source', 'online')
            ->with(['customer', 'branch', 'payments', 'shopPrescriptions']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Section::make('Customer')
                    ->schema([
                        TextInput::make('customer.name')->label('Name')->disabled(),
                        TextInput::make('customer.phone')->label('Phone')->disabled(),
                        TextInput::make('customer.address')->label('Address')->disabled()->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Order')
                    ->schema([
                        TextInput::make('external_ref')->label('Order ref (NOOP)')->disabled(),
                        TextInput::make('chapa_reference')->label('Chapa reference')->disabled(),
                        TextInput::make('total_amount')->prefix($currency)->disabled(),
                        TextInput::make('payment_status')->disabled(),
                        Select::make('shipping_status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'shipped' => 'Shipped',
                                'delivered' => 'Delivered',
                                'cancelled' => 'Cancelled',
                            ])
                            ->disabled(),
                        Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'processing' => 'Processing',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->disabled(),
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
                Tables\Columns\TextColumn::make('id')->label('#')->sortable(),
                Tables\Columns\TextColumn::make('external_ref')
                    ->label('Order ref')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('chapa_reference')
                    ->label('Chapa ref')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.phone')
                    ->label('Phone')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        'unpaid' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('shipping_status')
                    ->label('Delivery')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : 'Pending')
                    ->color(fn (?string $state): string => match ($state) {
                        'delivered' => 'success',
                        'shipped' => 'info',
                        'confirmed' => 'warning',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('payment_status')
                    ->options([
                        'paid' => 'Paid',
                        'partial' => 'Partial',
                        'unpaid' => 'Unpaid',
                    ]),
                Tables\Filters\SelectFilter::make('shipping_status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'shipped' => 'Shipped',
                        'delivered' => 'Delivered',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('markConfirmed')
                    ->label('Confirm')
                    ->icon('heroicon-o-check')
                    ->color('warning')
                    ->visible(fn (Order $record): bool => ($record->shipping_status ?? 'pending') === 'pending')
                    ->action(function (Order $record): void {
                        $record->update(['shipping_status' => 'confirmed', 'status' => 'processing']);
                        Notification::make()->success()->title('Order confirmed')->send();
                    }),
                Action::make('markShipped')
                    ->label('Ship')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (Order $record): bool => in_array($record->shipping_status, ['pending', 'confirmed', null], true))
                    ->action(function (Order $record): void {
                        $record->update(['shipping_status' => 'shipped', 'status' => 'processing']);
                        Notification::make()->success()->title('Marked shipped')->send();
                    }),
                Action::make('markDelivered')
                    ->label('Delivered')
                    ->icon('heroicon-o-home')
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->shipping_status !== 'delivered')
                    ->action(function (Order $record): void {
                        $record->update(['shipping_status' => 'delivered', 'status' => 'completed']);
                        Notification::make()->success()->title('Marked delivered')->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrderItemsRelationManager::class,
            ShopPrescriptionsRelationManager::class,
            PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebsiteOrders::route('/'),
            'view' => Pages\ViewWebsiteOrder::route('/{record}'),
        ];
    }
}
