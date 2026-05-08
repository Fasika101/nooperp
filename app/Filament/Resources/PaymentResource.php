<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Setting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Select::make('order_id')
                    ->relationship(
                        'order',
                        'id',
                        fn ($query) => $query
                            ->when(auth()->user()?->isBranchRestricted(), fn ($query) => $query->whereIn('branch_id', auth()->user()->branchIds()))
                            ->orderBy('id', 'desc')
                    )
                    ->required()
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(fn ($record) => "Order #{$record->id} - {$record->customer?->name}"),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->prefix($currency)
                    ->minValue(0),
                Select::make('payment_type_id')
                    ->relationship('paymentType', 'name', fn ($query) => $query
                        ->where('is_active', true)
                        ->when(auth()->user()?->isBranchRestricted(), fn ($query) => $query->forAnyBranch(auth()->user()->branchIds()))
                        ->orderBy('name'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(fn ($state, Set $set) => $set('payment_method', PaymentType::find($state)?->name ?? '')),
                TextInput::make('payment_method')
                    ->label('Payment Method')
                    ->disabled()
                    ->dehydrated()
                    ->required()
                    ->helperText('Synced from the selected payment type.'),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ])
                    ->default('pending')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.id')
                    ->label('Order')
                    ->formatStateUsing(fn ($state, $record) => "Order #{$state}"),
                Tables\Columns\TextColumn::make('order.customer.name')
                    ->label('Customer'),
                Tables\Columns\TextColumn::make('amount')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('paymentType.name')
                    ->label('Payment Type')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'completed' => 'success',
                        'failed' => 'danger',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
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
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->isBranchRestricted()) {
            $query->whereIn('branch_id', $user->branchIds());
        }

        return $query;
    }
}
