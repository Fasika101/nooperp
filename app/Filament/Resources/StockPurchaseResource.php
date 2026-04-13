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
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
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

        $recalcRestockTotal = function (Get $get, Set $set): void {
            $sum = 0;
            foreach (($get('restock_allocations') ?? []) as $line) {
                $sum += (int) ($line['quantity'] ?? 0);
            }
            $set('total_cost', round($sum * (float) ($get('unit_cost') ?: 0), 2));
        };

        return $schema
            ->components([
                Select::make('product_id')
                    ->relationship('product', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set, Get $get) use ($recalcRestockTotal) {
                        $product = Product::query()->find($state);

                        $set('unit_cost', null);
                        $set('sale_price', $product ? (float) $product->price : null);
                        $recalcRestockTotal($get, $set);
                    }),
                Repeater::make('restock_allocations')
                    ->label('Restock by branch')
                    ->schema([
                        Select::make('branch_id')
                            ->label('Branch')
                            ->options(fn () => Branch::query()
                                ->where('is_active', true)
                                ->when(auth()->user()?->isBranchRestricted(), fn ($query) => $query->whereKey(auth()->user()?->branch_id))
                                ->orderByDesc('is_default')
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->user()?->branch_id ?: Branch::getDefaultBranch()?->id)
                            ->disabled(fn () => auth()->user()?->isBranchRestricted() ?? false),
                        TextInput::make('quantity')
                            ->label('Units')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                    ])
                    ->defaultItems(1)
                    ->addActionLabel('Add branch')
                    ->reorderable(false)
                    ->columnSpanFull()
                    ->live(onBlur: true)
                    ->afterStateUpdated($recalcRestockTotal)
                    ->helperText('How many units go to each branch. Add a row per branch. One payment below covers the full quantity.'),
                TextInput::make('unit_cost')
                    ->label('Unit Cost')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix($currency)
                    ->live(onBlur: true)
                    ->helperText('Cost per unit you paid for this restock')
                    ->afterStateUpdated($recalcRestockTotal),
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
                    ->dehydrated(false)
                    ->prefix($currency)
                    ->default(0)
                    ->live()
                    ->afterStateHydrated($recalcRestockTotal),
                DatePicker::make('date')
                    ->required()
                    ->default(now()),
                Select::make('bank_account_id')
                    ->label('Pay From Account')
                    ->options(function (Get $get) {
                        $branchIds = array_values(array_unique(array_filter(array_map('intval', array_column($get('restock_allocations') ?? [], 'branch_id')))));
                        $q = BankAccount::query();
                        if (count($branchIds) > 1) {
                            $q->forAllBranches($branchIds);
                        } elseif (count($branchIds) === 1) {
                            $q->forBranch($branchIds[0]);
                        }
                        if (auth()->user()?->isBranchRestricted()) {
                            $q->forBranch((int) auth()->user()->branch_id);
                        }

                        return $q->orderByDesc('is_default')
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn () => BankAccount::getDefaultAccountForBranch(Branch::getDefaultBranch()?->id)?->id)
                    ->helperText('One deduction for the total cost; stock is split across branches above.'),
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
