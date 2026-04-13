<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions;

use App\Filament\Clusters\ProductOptionsCluster;
use App\Models\ProductOption;
use App\Models\Setting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

abstract class ProductOptionTypeResource extends Resource
{
    protected static ?string $model = ProductOption::class;

    protected static ?string $cluster = ProductOptionsCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static bool $isGloballySearchable = false;

    abstract public static function optionType(): string;

    abstract public static function typeNavigationSort(): int;

    protected static function navigationLabelForType(string $type): string
    {
        return match ($type) {
            ProductOption::TYPE_BRAND => 'Brands',
            ProductOption::TYPE_SIZE => 'Sizes',
            ProductOption::TYPE_COLOR => 'Colors',
            ProductOption::TYPE_GENDER => 'Gender',
            ProductOption::TYPE_MATERIAL => 'Materials',
            ProductOption::TYPE_SHAPE => 'Shapes',
            default => ProductOption::getTypeOptions()[$type] ?? ucfirst($type),
        };
    }

    public static function getNavigationLabel(): string
    {
        return static::navigationLabelForType(static::optionType());
    }

    public static function getModelLabel(): string
    {
        $type = static::optionType();

        return ProductOption::getTypeOptions()[$type] ?? ucfirst($type);
    }

    public static function getPluralModelLabel(): string
    {
        return static::getNavigationLabel();
    }

    public static function getNavigationSort(): ?int
    {
        return static::typeNavigationSort();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return parent::shouldRegisterNavigation()
            && Setting::isProductOptionFieldEnabled(static::optionType());
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', static::optionType());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Hidden::make('type')
                    ->default(static::optionType())
                    ->required(),
                TextInput::make('name')
                    ->maxLength(255)
                    ->required()
                    ->visibleOn('edit'),
                Textarea::make('option_values')
                    ->label('Values')
                    ->placeholder('e.g. Red, Blue, Green')
                    ->helperText('Separate values with commas or new lines. All are added at once.')
                    ->rows(4)
                    ->columnSpanFull()
                    ->visibleOn('create'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('name')
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
        return [];
    }

    /**
     * @return class-string<Page>
     */
    abstract protected static function getListPage(): string;

    /**
     * @return class-string<Page>
     */
    abstract protected static function getCreatePage(): string;

    /**
     * @return class-string<Page>
     */
    abstract protected static function getEditPage(): string;

    public static function getPages(): array
    {
        return [
            'index' => static::getListPage()::route('/'),
            'create' => static::getCreatePage()::route('/create'),
            'edit' => static::getEditPage()::route('/{record}/edit'),
        ];
    }
}
