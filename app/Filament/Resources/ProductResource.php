<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    public static function infolist(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->columns(1)
            ->components([
                Section::make('Product')
                    ->schema([
                        ImageEntry::make('image')
                            ->disk('public')
                            ->height(180)
                            ->columnSpanFull()
                            ->defaultImageUrl('https://ui-avatars.com/api/?name=Product&color=7F9CF5&background=EBF4FF'),
                        TextEntry::make('name')
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('category.name')
                            ->label('Category')
                            ->placeholder('—'),
                        TextEntry::make('is_service')
                            ->label('Product type')
                            ->badge()
                            ->formatStateUsing(fn (?bool $state): string => $state ? 'Service (non-inventory)' : 'Inventory'),
                    ])
                    ->columns(2),
                Section::make('Attributes')
                    ->schema([
                        TextEntry::make('brand.name')
                            ->label('Brand')
                            ->placeholder('—')
                            ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_BRAND)),
                        TextEntry::make('size_option_id')
                            ->label('Sizes')
                            ->formatStateUsing(function (TextEntry $component, $state): string {
                                $record = $component->getRecord();

                                return $record instanceof Product
                                    ? self::formatAttachedOptionsLabel($record, ProductOption::TYPE_SIZE)
                                    : '—';
                            })
                            ->placeholder('—')
                            ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_SIZE)),
                        TextEntry::make('color_option_id')
                            ->label('Colors')
                            ->formatStateUsing(function (TextEntry $component, $state): string {
                                $record = $component->getRecord();

                                return $record instanceof Product
                                    ? self::formatAttachedOptionsLabel($record, ProductOption::TYPE_COLOR)
                                    : '—';
                            })
                            ->placeholder('—')
                            ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_COLOR)),
                        TextEntry::make('gender.name')
                            ->label('Gender')
                            ->placeholder('—')
                            ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_GENDER)),
                        TextEntry::make('material.name')
                            ->label('Material')
                            ->placeholder('—')
                            ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_MATERIAL)),
                        TextEntry::make('shape.name')
                            ->label('Shape')
                            ->placeholder('—')
                            ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_SHAPE)),
                    ])
                    ->columns(2)
                    ->collapsed(false),
                Section::make('Frame size')
                    ->description('Eyeglass measurements (mm).')
                    ->schema([
                        TextEntry::make('lens_width_mm')
                            ->label('Lens width')
                            ->suffix(' mm')
                            ->placeholder('—'),
                        TextEntry::make('bridge_width_mm')
                            ->label('Bridge width')
                            ->suffix(' mm')
                            ->placeholder('—'),
                        TextEntry::make('temple_length_mm')
                            ->label('Temple length')
                            ->suffix(' mm')
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Section::make('Pricing & stock')
                    ->schema([
                        TextEntry::make('cost_price')
                            ->label('Cost price')
                            ->money($currency)
                            ->placeholder('—'),
                        TextEntry::make('price')
                            ->label('Sale price')
                            ->money($currency),
                        TextEntry::make('stock')
                            ->label('Total stock')
                            ->badge()
                            ->color(fn (int $state): string => match (true) {
                                $state <= 0 => 'danger',
                                $state <= 10 => 'warning',
                                default => 'success',
                            }),
                        RepeatableEntry::make('branchStocks')
                            ->label('By branch')
                            ->schema([
                                TextEntry::make('branch.name')
                                    ->label('Branch'),
                                TextEntry::make('productVariant')
                                    ->label('Variant')
                                    ->formatStateUsing(fn ($state, BranchProductStock $record): string => $record->productVariant?->label() ?? '—')
                                    ->placeholder('—'),
                                TextEntry::make('quantity')
                                    ->label('Qty'),
                                TextEntry::make('avg_cost')
                                    ->label('Avg cost')
                                    ->money($currency)
                                    ->placeholder('—'),
                            ])
                            ->columns(4),
                    ])
                    ->columns(2),
                Section::make('Record')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

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
                    ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_SIZE))
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $set('size_stock_quantities', []);
                        $set('variant_stock_quantities', []);
                        $ids = array_filter(array_map('intval', (array) $state));
                        if (count($ids) > 1) {
                            $set('stock', 0);
                        }
                    }),
                Select::make('color_option_ids')
                    ->label('Colors')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => ProductOption::forType(ProductOption::TYPE_COLOR)->orderBy('name')->pluck('name', 'id'))
                    ->helperText('Select every color option. At POS, staff pick one color when more than one is listed.')
                    ->visible(fn (): bool => Setting::isProductOptionFieldEnabled(ProductOption::TYPE_COLOR))
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $set('color_stock_quantities', []);
                        $set('variant_stock_quantities', []);
                        $ids = array_filter(array_map('intval', (array) $state));
                        if (count($ids) > 1) {
                            $set('stock', 0);
                        }
                    }),
                Repeater::make('color_stock_quantities')
                    ->label('Units per color')
                    ->schema([
                        Select::make('color_option_id')
                            ->label('Color')
                            ->options(function (Get $get) {
                                $ids = array_filter(array_map('intval', (array) ($get('../../color_option_ids') ?? [])));

                                return $ids === []
                                    ? []
                                    : ProductOption::query()->whereIn('id', $ids)->orderBy('name')->pluck('name', 'id')->all();
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live(onBlur: true),
                    ])
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $sum = (int) collect($state ?? [])->sum(fn ($row) => (int) ($row['quantity'] ?? 0));
                        $set('stock', $sum);
                    })
                    ->addActionLabel('Add color quantity')
                    ->reorderable(false)
                    ->columnSpanFull()
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create'
                        && count((array) ($get('color_option_ids') ?? [])) > 1
                        && count((array) ($get('size_option_ids') ?? [])) <= 1)
                    ->helperText('Pick a color, enter how many units, then add another row for the next color. Total stock is calculated below.'),
                Repeater::make('size_stock_quantities')
                    ->label('Units per size')
                    ->schema([
                        Select::make('size_option_id')
                            ->label('Size')
                            ->options(function (Get $get) {
                                $ids = array_filter(array_map('intval', (array) ($get('../../size_option_ids') ?? [])));

                                return $ids === []
                                    ? []
                                    : ProductOption::query()->whereIn('id', $ids)->orderBy('name')->pluck('name', 'id')->all();
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live(onBlur: true),
                    ])
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $sum = (int) collect($state ?? [])->sum(fn ($row) => (int) ($row['quantity'] ?? 0));
                        $set('stock', $sum);
                    })
                    ->addActionLabel('Add size quantity')
                    ->reorderable(false)
                    ->columnSpanFull()
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create'
                        && count((array) ($get('size_option_ids') ?? [])) > 1
                        && count((array) ($get('color_option_ids') ?? [])) <= 1)
                    ->helperText('Pick a size, enter how many units, then add another row for the next size. Total stock is calculated below.'),
                Repeater::make('variant_stock_quantities')
                    ->label('Units per color and size')
                    ->schema([
                        Select::make('color_option_id')
                            ->label('Color')
                            ->options(function (Get $get) {
                                $ids = array_filter(array_map('intval', (array) ($get('../../color_option_ids') ?? [])));

                                return $ids === []
                                    ? []
                                    : ProductOption::query()->whereIn('id', $ids)->orderBy('name')->pluck('name', 'id')->all();
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                        Select::make('size_option_id')
                            ->label('Size')
                            ->options(function (Get $get) {
                                $ids = array_filter(array_map('intval', (array) ($get('../../size_option_ids') ?? [])));

                                return $ids === []
                                    ? []
                                    : ProductOption::query()->whereIn('id', $ids)->orderBy('name')->pluck('name', 'id')->all();
                            })
                            ->required()
                            ->searchable()
                            ->preload(),
                        TextInput::make('quantity')
                            ->label('Quantity')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live(onBlur: true),
                    ])
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $sum = (int) collect($state ?? [])->sum(fn ($row) => (int) ($row['quantity'] ?? 0));
                        $set('stock', $sum);
                    })
                    ->addActionLabel('Add color & size quantity')
                    ->reorderable(false)
                    ->columnSpanFull()
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create'
                        && count((array) ($get('color_option_ids') ?? [])) > 1
                        && count((array) ($get('size_option_ids') ?? [])) > 1)
                    ->helperText('Enter units for each color and size combination. Total stock is calculated below.'),
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
                Section::make('Frame size')
                    ->description('Optional. Typical eyeglass box size, e.g. 54 □ 18 □ 140 (mm).')
                    ->schema([
                        TextInput::make('lens_width_mm')
                            ->label('Lens width')
                            ->numeric()
                            ->suffix('mm')
                            ->minValue(0)
                            ->maxValue(999)
                            ->step(0.1)
                            ->placeholder('e.g. 54'),
                        TextInput::make('bridge_width_mm')
                            ->label('Bridge width')
                            ->numeric()
                            ->suffix('mm')
                            ->minValue(0)
                            ->maxValue(999)
                            ->step(0.1)
                            ->placeholder('e.g. 18'),
                        TextInput::make('temple_length_mm')
                            ->label('Temple length')
                            ->numeric()
                            ->suffix('mm')
                            ->minValue(0)
                            ->maxValue(999)
                            ->step(0.1)
                            ->placeholder('e.g. 140'),
                    ])
                    ->columns(3)
                    ->collapsed(false),
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
                    ->label('Total stock')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->live(onBlur: true)
                    ->disabled(fn (string $operation, Get $get): bool => $operation === 'create'
                        && (count((array) ($get('color_option_ids') ?? [])) > 1
                            || count((array) ($get('size_option_ids') ?? [])) > 1))
                    ->dehydrated(true)
                    ->helperText(function (string $operation, Get $get): ?string {
                        if ($operation !== 'create') {
                            return null;
                        }
                        $colorN = count((array) ($get('color_option_ids') ?? []));
                        $sizeN = count((array) ($get('size_option_ids') ?? []));
                        if ($colorN > 1 && $sizeN > 1) {
                            return 'This total is the sum of “Units per color and size” above.';
                        }
                        if ($colorN > 1) {
                            return 'This total is the sum of “Units per color” above.';
                        }
                        if ($sizeN > 1) {
                            return 'This total is the sum of “Units per size” above.';
                        }

                        return null;
                    }),
                Repeater::make('initial_stock_allocations')
                    ->label('Split initial stock by branch')
                    ->schema([
                        Select::make('branch_id')
                            ->label('Branch')
                            ->options(fn () => Branch::query()
                                ->where('is_active', true)
                                ->when(auth()->user()?->isBranchRestricted(), fn ($query) => $query->whereIn('id', auth()->user()->branchIds()))
                                ->orderByDesc('is_default')
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->default(fn () => auth()->user()?->branch_id ?: Branch::getDefaultBranch()?->id)
                            ->disabled(fn () => auth()->user()?->isBranchRestricted() && count(auth()->user()->branchIds()) === 1),
                        Select::make('color_option_id')
                            ->label('Color')
                            ->options(function (Get $get) {
                                $ids = array_filter(array_map('intval', (array) ($get('../../color_option_ids') ?? [])));

                                return $ids === []
                                    ? []
                                    : ProductOption::query()->whereIn('id', $ids)->orderBy('name')->pluck('name', 'id')->all();
                            })
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => count((array) ($get('../../color_option_ids') ?? [])) > 1)
                            ->required(fn (Get $get): bool => count((array) ($get('../../color_option_ids') ?? [])) > 1),
                        Select::make('size_option_id')
                            ->label('Size')
                            ->options(function (Get $get) {
                                $ids = array_filter(array_map('intval', (array) ($get('../../size_option_ids') ?? [])));

                                return $ids === []
                                    ? []
                                    : ProductOption::query()->whereIn('id', $ids)->orderBy('name')->pluck('name', 'id')->all();
                            })
                            ->searchable()
                            ->preload()
                            ->visible(fn (Get $get): bool => count((array) ($get('../../size_option_ids') ?? [])) > 1)
                            ->required(fn (Get $get): bool => count((array) ($get('../../size_option_ids') ?? [])) > 1),
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
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && (int) ($get('stock') ?? 0) > 0)
                    ->helperText('Assign how many units go to each branch. When multiple colors or sizes are selected, pick color and size on each row. The sum must equal Stock above.'),
                Select::make('initial_stock_bank_account_id')
                    ->label('Pay Initial Stock From')
                    ->options(fn () => BankAccount::query()
                        ->when(auth()->user()?->isBranchRestricted(), fn ($query) => $query->forAnyBranch(auth()->user()->branchIds()))
                        ->orderByDesc('is_default')
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->default(fn () => BankAccount::getDefaultAccountForBranch(Branch::getDefaultBranch()?->id)?->id)
                    ->visible(fn (string $operation, Get $get): bool => $operation === 'create' && (int) ($get('stock') ?? 0) > 0)
                    ->required(fn (string $operation, Get $get): bool => $operation === 'create' && (int) ($get('stock') ?? 0) > 0)
                    ->helperText('One payment for the full initial purchase; stock is split across branches above.'),
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
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name='.urlencode($record->name).'&color=7F9CF5&background=EBF4FF'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Product $record): string => static::getUrl('view', ['record' => $record])),
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
                Tables\Columns\TextColumn::make('frame_measurements')
                    ->label('Frame mm')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->getStateUsing(function (Product $record): string {
                        $parts = [];
                        if ($record->lens_width_mm !== null) {
                            $parts[] = (string) $record->lens_width_mm;
                        }
                        if ($record->bridge_width_mm !== null) {
                            $parts[] = (string) $record->bridge_width_mm;
                        }
                        if ($record->temple_length_mm !== null) {
                            $parts[] = (string) $record->temple_length_mm;
                        }

                        return $parts !== [] ? implode('-', $parts) : '';
                    }),
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
                        ->when(auth()->user()?->isBranchRestricted(), fn ($query) => $query->whereIn('id', auth()->user()->branchIds()))
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                    ->query(function ($query, array $data) {
                        $branchId = $data['value'] ?? null;

                        return $query->when($branchId, fn ($query) => $query->whereHas('branchStocks', fn ($query) => $query->where('branch_id', $branchId)->where('quantity', '>=', 1)));
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
                ViewAction::make(),
                Action::make('restock')
                    ->label('Restock')
                    ->icon('heroicon-o-arrow-path')
                    ->url(fn (Product $record) => StockPurchaseResource::getUrl('create', ['product_id' => $record->id]))
                    ->color('success'),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('delete')
                        ->label('Delete selected')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Delete selected products')
                        ->modalDescription('This will refund the remaining inventory value (stock × cost price) back to the bank account and cannot be undone.')
                        ->form([
                            Textarea::make('deletion_notes')
                                ->label('Reason for deletion')
                                ->placeholder('e.g. Discontinued, damaged stock, supplier issue…')
                                ->rows(3),
                        ])
                        ->action(function ($records, array $data): void {
                            foreach ($records as $record) {
                                $record->deletion_notes = $data['deletion_notes'] ?? null;
                                $record->delete();
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
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
            'view' => Pages\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }

    protected static function formatAttachedOptionsLabel(Product $record, string $type): string
    {
        $record->loadMissing('attachedProductOptions');

        $fromPivot = $record->attachedProductOptions
            ->where('type', $type)
            ->pluck('name')
            ->sort()
            ->values();

        if ($fromPivot->isNotEmpty()) {
            return $fromPivot->implode(', ');
        }

        $fallback = match ($type) {
            ProductOption::TYPE_SIZE => $record->size?->name,
            ProductOption::TYPE_COLOR => $record->color?->name,
            default => null,
        };

        return $fallback !== null && $fallback !== '' ? (string) $fallback : '—';
    }
}
