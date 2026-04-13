<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductOptionResource\Pages;
use App\Models\ProductOption;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductOptionResource extends Resource
{
    protected static ?string $model = ProductOption::class;

    /** Flat list under its own URL; primary UI is the Product options cluster. */
    protected static ?string $slug = 'product-options-legacy';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Product Options';

    protected static ?string $modelLabel = 'Product Option';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->options(ProductOption::getTypeOptions())
                    ->required()
                    ->searchable()
                    ->preload(),
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
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state, ProductOption $record): string => $record->getTypeLabel())
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(ProductOption::getTypeOptions()),
            ])
            ->defaultSort('type')
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductOptions::route('/'),
            'create' => Pages\CreateProductOption::route('/create'),
            'edit' => Pages\EditProductOption::route('/{record}/edit'),
        ];
    }
}
