<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockPurchaseResource\Pages;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Product;
use App\Models\Setting;
use App\Models\StockPurchase;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StockPurchaseResource extends Resource
{
    protected static ?string $model = StockPurchase::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Restock';

    protected static ?string $modelLabel = 'Stock Purchase';

    protected static ?string $pluralModelLabel = 'Stock Purchases';

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        $product = Product::query()->find($state);

                        $set('unit_cost', null);
                        $set('sale_price', $product ? (float) $product->price : null);
                    }),
                TextInput::make('quantity')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Get $get, Set $set) => $set('total_cost', round((float) $get('quantity') * (float) $get('unit_cost'), 2))),
                TextInput::make('unit_cost')
                    ->label('Unit Cost')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix($currency)
                    ->live(onBlur: true)
                    ->helperText('Cost per unit you paid for this restock')
                    ->afterStateUpdated(fn (Get $get, Set $set) => $set('total_cost', round((float) $get('quantity') * (float) $get('unit_cost'), 2))),
                TextInput::make('sale_price')
                    ->label('New Sale Price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix($currency)
                    ->helperText('Updated selling price after this restock.'),
                TextInput::make('total_cost')
                    ->label('Total Cost')
                    ->disabled()
                    ->dehydrated()
                    ->prefix($currency)
                    ->default(0)
                    ->live()
                    ->afterStateHydrated(function (Get $get, Set $set) {
                        $qty = (float) ($get('quantity') ?: 0);
                        $cost = (float) ($get('unit_cost') ?: 0);
                        $set('total_cost', round($qty * $cost, 2));
                    }),
                DatePicker::make('date')
                    ->required()
                    ->default(now()),
                Select::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name', fn ($query) => $query->where('is_active', true)->orderByDesc('is_default')->orderBy('name'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->default(fn () => auth()->user()?->branch_id ?: Branch::getDefaultBranch()?->id)
                    ->disabled(fn () => auth()->user()?->isBranchRestricted() ?? false)
                    ->dehydrated(),
                Select::make('bank_account_id')
                    ->label('Pay From Account')
                    ->options(fn (Get $get) => BankAccount::query()
                        ->when($get('branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
                        ->orderByDesc('is_default')
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn () => BankAccount::getDefaultAccountForBranch(Branch::getDefaultBranch()?->id)?->id)
                    ->helperText('The restock cost will be deducted from this account.'),
                TextInput::make('vendor')
                    ->maxLength(255)
                    ->placeholder('Supplier or vendor name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_cost')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Sale Price')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_cost')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('bankAccount.name')
                    ->label('Paid From')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor')
                    ->placeholder('—'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockPurchases::route('/'),
            'create' => Pages\CreateStockPurchase::route('/create'),
            'view' => Pages\ViewStockPurchase::route('/{record}'),
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

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
