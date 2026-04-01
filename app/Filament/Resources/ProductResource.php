<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\StockPurchaseResource;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                FileUpload::make('image')
                    ->image()
                    ->disk('public')
                    ->directory('products')
                    ->visibility('public')
                    ->imagePreviewHeight('200')
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('brand_option_id')
                    ->label('Brand')
                    ->relationship('brand', 'name', fn ($query) => $query->forType(ProductOption::TYPE_BRAND)->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->placeholder('No brand')
                    ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_BRAND)),
                Select::make('size_option_ids')
                    ->label('Sizes')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => ProductOption::forType(ProductOption::TYPE_SIZE)->orderBy('name')->pluck('name', 'id'))
                    ->helperText('Select every size this product comes in. At POS, staff pick one size per sale when more than one is listed.')
                    ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_SIZE)),
                Select::make('color_option_ids')
                    ->label('Colors')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => ProductOption::forType(ProductOption::TYPE_COLOR)->orderBy('name')->pluck('name', 'id'))
                    ->helperText('Select every color option. At POS, staff pick one color when more than one is listed.')
                    ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_COLOR)),
                Select::make('gender_option_id')
                    ->label('Gender')
                    ->relationship('gender', 'name', fn ($query) => $query->forType(ProductOption::TYPE_GENDER)->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->placeholder('No gender')
                    ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_GENDER)),
                Select::make('material_option_id')
                    ->label('Material')
                    ->relationship('material', 'name', fn ($query) => $query->forType(ProductOption::TYPE_MATERIAL)->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->placeholder('No material')
                    ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_MATERIAL)),
                Select::make('shape_option_id')
                    ->label('Shape')
                    ->relationship('shape', 'name', fn ($query) => $query->forType(ProductOption::TYPE_SHAPE)->orderBy('name'))
                    ->searchable()
                    ->preload()
                    ->placeholder('No shape')
                    ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_SHAPE)),
                TextInput::make('original_price')
                    ->label('Original/List Price')
                    ->numeric()
                    ->prefix($currency)
                    ->minValue(0)
                    ->helperText('List price before discount (optional)'),
                TextInput::make('cost_price')
                    ->label('Cost Price')
                    ->numeric()
                    ->prefix($currency)
                    ->minValue(0)
                    ->helperText('What you paid to acquire this product (for profit calculation)')
                    ->required(fn (string $operation, Get $get): bool => $operation === 'create' && (int) ($get('stock') ?? 0) > 0),
                TextInput::make('price')
                    ->label('Sale Price')
                    ->required()
                    ->numeric()
                    ->prefix($currency)
                    ->minValue(0)
                    ->helperText('Actual selling price at POS'),
                TextInput::make('stock')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->live(onBlur: true),
                Select::make('initial_stock_branch_id')
                    ->label('Initial Stock Branch')
                    ->options(fn () => Branch::query()
                        ->where('is_active', true)
                        ->when(auth()->user()?->isBranchRestricted(), fn ($query) => $query->whereKey(auth()->user()?->branch_id))
                        ->orderByDesc('is_default')
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->default(fn () => auth()->user()?->branch_id ?: Branch::getDefaultBranch()?->id)
                    ->disabled(fn () => auth()->user()?->isBranchRestricted() ?? false)
                    ->dehydrated()
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && (int) ($get('stock') ?? 0) > 0)
                    ->required(fn (string $operation, Get $get): bool => $operation === 'create' && (int) ($get('stock') ?? 0) > 0),
                Select::make('initial_stock_bank_account_id')
                    ->label('Pay Initial Stock From')
                    ->options(fn (Get $get) => BankAccount::query()
                        ->when($get('initial_stock_branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
                        ->orderByDesc('is_default')
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->default(fn () => BankAccount::getDefaultAccountForBranch(Branch::getDefaultBranch()?->id)?->id)
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && (int) ($get('stock') ?? 0) > 0)
                    ->required(fn (string $operation, Get $get): bool => $operation === 'create' && (int) ($get('stock') ?? 0) > 0)
                    ->helperText('Initial stock will be deducted from this account.'),
                DatePicker::make('initial_stock_date')
                    ->label('Initial Stock Date')
                    ->default(now())
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && (int) ($get('stock') ?? 0) > 0)
                    ->required(fn (string $operation, Get $get): bool => $operation === 'create' && (int) ($get('stock') ?? 0) > 0),
                TextInput::make('initial_stock_vendor')
                    ->label('Initial Stock Vendor')
                    ->maxLength(255)
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && (int) ($get('stock') ?? 0) > 0)
                    ->placeholder('Supplier or vendor name'),
                Toggle::make('is_service')
                    ->label('Non-inventory (service) product')
                    ->helperText('Hidden from the POS product grid and excluded from branch stock checks. Keep enabled only for internal lines such as the POS optical lens service product.')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('attachedProductOptions'))
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->name) . '&color=7F9CF5&background=EBF4FF'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('brand.name')
                    ->label('Brand')
                    ->placeholder('—')
                    ->sortable()
                    ->hidden(fn (): bool => ! Setting::isProductOptionFieldEnabled(ProductOption::TYPE_BRAND)),
                Tables\Columns\TextColumn::make('color_options_list')
                    ->label('Colors')
                    ->placeholder('—')
                    ->toggleable()
                    ->getStateUsing(function (Product $record): string {
                        $record->loadMissing('attachedProductOptions');
                        $names = $record->attachedProductOptions->where('type', ProductOption::TYPE_COLOR)->pluck('name')->all();

                        return $names !== [] ? implode(', ', $names) : (string) ($record->color?->name ?? '');
                    })
                    ->hidden(fn (): bool => ! Setting::isProductOptionFieldEnabled(ProductOption::TYPE_COLOR)),
                Tables\Columns\TextColumn::make('size_options_list')
                    ->label('Sizes')
                    ->placeholder('—')
                    ->toggleable()
                    ->getStateUsing(function (Product $record): string {
                        $record->loadMissing('attachedProductOptions');
                        $names = $record->attachedProductOptions->where('type', ProductOption::TYPE_SIZE)->pluck('name')->all();

                        return $names !== [] ? implode(', ', $names) : (string) ($record->size?->name ?? '');
                    })
                    ->hidden(fn (): bool => ! Setting::isProductOptionFieldEnabled(ProductOption::TYPE_SIZE)),
                Tables\Columns\TextColumn::make('original_price')
                    ->label('List')
                    ->money($currency)
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('cost_price')
                    ->label('Cost')
                    ->money($currency)
                    ->sortable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Sale Price')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state <= 10 => 'warning',
                        default => 'success',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->relationship('category', 'name')
                    ->label('Category'),
                Tables\Filters\SelectFilter::make('branch')
                    ->label('Branch')
                    ->options(fn () => Branch::query()
                        ->when(auth()->user()?->isBranchRestricted(), fn ($query) => $query->whereKey(auth()->user()?->branch_id))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->query(function ($query, array $data) {
                        $branchId = $data['value'] ?? null;

                        return $query->when($branchId, fn ($query) => $query->whereHas('branchStocks', fn ($query) => $query->where('branch_id', $branchId)->where('quantity', '>', 0)));
                    }),
                Tables\Filters\SelectFilter::make('brand_option_id')
                    ->relationship('brand', 'name')
                    ->label('Brand')
                    ->hidden(fn (): bool => ! Setting::isProductOptionFieldEnabled(ProductOption::TYPE_BRAND)),
                Tables\Filters\SelectFilter::make('color_option_id')
                    ->relationship('color', 'name')
                    ->label('Color')
                    ->hidden(fn (): bool => ! Setting::isProductOptionFieldEnabled(ProductOption::TYPE_COLOR)),
                Tables\Filters\SelectFilter::make('size_option_id')
                    ->relationship('size', 'name')
                    ->label('Size')
                    ->hidden(fn (): bool => ! Setting::isProductOptionFieldEnabled(ProductOption::TYPE_SIZE)),
                Tables\Filters\SelectFilter::make('gender_option_id')
                    ->relationship('gender', 'name')
                    ->label('Gender')
                    ->hidden(fn (): bool => ! Setting::isProductOptionFieldEnabled(ProductOption::TYPE_GENDER)),
                Tables\Filters\SelectFilter::make('material_option_id')
                    ->relationship('material', 'name')
                    ->label('Material')
                    ->hidden(fn (): bool => ! Setting::isProductOptionFieldEnabled(ProductOption::TYPE_MATERIAL)),
                Tables\Filters\SelectFilter::make('shape_option_id')
                    ->relationship('shape', 'name')
                    ->label('Shape')
                    ->hidden(fn (): bool => ! Setting::isProductOptionFieldEnabled(ProductOption::TYPE_SHAPE)),
            ])
            ->actions([
                Action::make('restock')
                    ->label('Restock')
                    ->icon('heroicon-o-arrow-path')
                    ->url(fn (Product $record) => StockPurchaseResource::getUrl('create', ['product_id' => $record->id]))
                    ->color('success'),
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
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
