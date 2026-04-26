<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\BranchStockTransferResource\Pages;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\BranchStockTransfer;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BranchStockTransferResource extends Resource
{
    protected static ?string $model = BranchStockTransfer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Branch stock transfers';

    protected static ?string $modelLabel = 'Branch stock transfer';

    protected static ?string $pluralModelLabel = 'Branch stock transfers';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('product_id')
                    ->label('Product')
                    ->relationship('product', 'name', fn ($query) => $query->where('is_service', false)->orderBy('name'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $productId = (int) $state;
                        $variants = ProductVariant::query()->where('product_id', $productId)->orderBy('id')->get();
                        if ($variants->count() === 1) {
                            $set('product_variant_id', (int) $variants->first()->id);
                        } else {
                            $set('product_variant_id', null);
                        }
                    })
                    ->helperText(fn (Get $get): ?string => self::productStockSummary((int) ($get('product_id') ?? 0))),
                Select::make('product_variant_id')
                    ->label('Variant')
                    ->options(fn (Get $get): array => self::variantOptionsForProduct((int) ($get('product_id') ?? 0)))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->visible(fn (Get $get): bool => self::productHasMultipleVariants((int) ($get('product_id') ?? 0)))
                    ->required(fn (Get $get): bool => self::productHasMultipleVariants((int) ($get('product_id') ?? 0))),
                Select::make('from_branch_id')
                    ->label('From branch')
                    ->options(fn () => Branch::query()
                        ->where('is_active', true)
                        ->when(auth()->user()?->isBranchRestricted(), fn (Builder $q) => $q->whereKey(auth()->user()?->branch_id))
                        ->orderByDesc('is_default')
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->default(fn () => auth()->user()?->branch_id ?: Branch::getDefaultBranch()?->id)
                    ->disabled(fn () => auth()->user()?->isBranchRestricted() ?? false)
                    ->helperText(fn (Get $get): ?string => self::branchAvailabilityLine(
                        (int) ($get('product_id') ?? 0),
                        (int) ($get('from_branch_id') ?? 0),
                        'from',
                        self::resolvedProductVariantIdForTransfer($get),
                    )),
                Select::make('to_branch_id')
                    ->label('To branch')
                    ->options(fn () => Branch::query()
                        ->where('is_active', true)
                        ->orderByDesc('is_default')
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->helperText(fn (Get $get): ?string => self::branchAvailabilityLine(
                        (int) ($get('product_id') ?? 0),
                        (int) ($get('to_branch_id') ?? 0),
                        'to',
                        self::resolvedProductVariantIdForTransfer($get),
                    )),
                TextInput::make('quantity')
                    ->label('Quantity to move')
                    ->required()
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(fn (Get $get): int => self::maxTransferableQuantity(
                        (int) ($get('product_id') ?? 0),
                        (int) ($get('from_branch_id') ?? 0),
                        self::resolvedProductVariantIdForTransfer($get),
                    ))
                    ->helperText(fn (Get $get): ?string => self::quantityHelperLine(
                        (int) ($get('product_id') ?? 0),
                        (int) ($get('from_branch_id') ?? 0),
                        self::resolvedProductVariantIdForTransfer($get),
                    )),
                Textarea::make('note')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product_variant_id')
                    ->label('Variant')
                    ->formatStateUsing(fn ($state, BranchStockTransfer $record): string => $record->productVariant?->label() ?? '—')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('fromBranch.name')
                    ->label('From')
                    ->sortable(),
                Tables\Columns\TextColumn::make('toBranch.name')
                    ->label('To')
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('By')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([])
            ->actions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranchStockTransfers::route('/'),
            'create' => Pages\CreateBranchStockTransfer::route('/create'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['product', 'productVariant.colorOption', 'productVariant.sizeOption', 'fromBranch', 'toBranch', 'user']);

        $user = auth()->user();
        if ($user?->isBranchRestricted()) {
            $bid = (int) $user->branch_id;
            $query->where(function (Builder $q) use ($bid): void {
                $q->where('from_branch_id', $bid)->orWhere('to_branch_id', $bid);
            });
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

    protected static function variantOptionsForProduct(int $productId): array
    {
        if ($productId <= 0) {
            return [];
        }

        return ProductVariant::query()
            ->where('product_id', $productId)
            ->with(['colorOption', 'sizeOption'])
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (ProductVariant $v): array => [$v->id => $v->label()])
            ->all();
    }

    protected static function productHasMultipleVariants(int $productId): bool
    {
        if ($productId <= 0) {
            return false;
        }

        return ProductVariant::query()->where('product_id', $productId)->count() > 1;
    }

    protected static function resolvedProductVariantIdForTransfer(Get $get): ?int
    {
        $pid = (int) ($get('product_id') ?? 0);
        if ($pid <= 0) {
            return null;
        }

        $variants = ProductVariant::query()->where('product_id', $pid)->orderBy('id')->get();
        if ($variants->count() === 1) {
            return (int) $variants->first()->id;
        }

        $raw = $get('product_variant_id');
        if ($raw === null || $raw === '') {
            return null;
        }

        $vid = (int) $raw;

        return $vid > 0 ? $vid : null;
    }

    protected static function stockAtBranch(int $productId, int $branchId, ?int $productVariantId = null): int
    {
        if ($productId <= 0 || $branchId <= 0) {
            return 0;
        }

        if ($productVariantId === null || $productVariantId <= 0) {
            return 0;
        }

        if (! ProductVariant::query()->where('product_id', $productId)->whereKey($productVariantId)->exists()) {
            return 0;
        }

        return (int) (BranchProductStock::query()
            ->where('branch_id', $branchId)
            ->where('product_variant_id', $productVariantId)
            ->value('quantity') ?? 0);
    }

    protected static function productStockSummary(int $productId): ?string
    {
        if ($productId <= 0) {
            return null;
        }

        $total = (int) Product::query()->whereKey($productId)->value('stock');
        $byBranch = BranchProductStock::query()
            ->whereHas('productVariant', fn ($q) => $q->where('product_id', $productId))
            ->where('quantity', '>', 0)
            ->with(['productVariant.colorOption', 'productVariant.sizeOption'])
            ->orderByDesc('quantity')
            ->limit(12)
            ->get(['branch_id', 'product_variant_id', 'quantity']);

        if ($byBranch->isEmpty()) {
            return "Total stock: {$total} (no branch rows yet).";
        }

        $branchNames = Branch::query()->whereIn('id', $byBranch->pluck('branch_id'))->pluck('name', 'id');

        $parts = $byBranch->map(function (BranchProductStock $row) use ($branchNames): string {
            $label = ($branchNames[$row->branch_id] ?? 'Branch #'.$row->branch_id);
            $variantLabel = $row->productVariant?->label();
            if ($variantLabel && $variantLabel !== 'Default') {
                $label .= " ({$variantLabel})";
            }

            return "{$label}: {$row->quantity}";
        })->implode('; ');

        return "Total stock: {$total}. By branch / variant: {$parts}.";
    }

    protected static function branchAvailabilityLine(int $productId, int $branchId, string $which, ?int $productVariantId = null): ?string
    {
        if ($productId <= 0 || $branchId <= 0) {
            return $which === 'from'
                ? 'Select a product and branch to see how many units you can move.'
                : 'Select a product and branch to see current stock there.';
        }

        $qty = self::stockAtBranch($productId, $branchId, $productVariantId);
        $branchName = Branch::query()->whereKey($branchId)->value('name') ?? 'this branch';

        return $which === 'from'
            ? "{$branchName}: {$qty} unit(s) available to transfer out for this variant."
            : "{$branchName}: {$qty} unit(s) in stock before this transfer for this variant.";
    }

    protected static function quantityHelperLine(int $productId, int $fromBranchId, ?int $productVariantId = null): ?string
    {
        if ($productId <= 0 || $fromBranchId <= 0) {
            return 'Choose product and source branch first.';
        }

        $avail = self::stockAtBranch($productId, $fromBranchId, $productVariantId);

        return $avail > 0
            ? "You can move up to {$avail} unit(s) from the source branch for this variant."
            : 'No stock at the source branch for this product and variant.';
    }

    protected static function maxTransferableQuantity(int $productId, int $fromBranchId, ?int $productVariantId = null): int
    {
        $avail = self::stockAtBranch($productId, $fromBranchId, $productVariantId);

        return max(1, $avail);
    }
}
