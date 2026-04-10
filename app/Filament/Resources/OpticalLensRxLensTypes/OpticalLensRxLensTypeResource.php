<?php

namespace App\Filament\Resources\OpticalLensRxLensTypes;

use App\Filament\Resources\OpticalLensRxLensTypes\Pages\ManageOpticalLensRxLensTypes;
use App\Models\OpticalLensRxLensType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OpticalLensRxLensTypeResource extends Resource
{
    protected static ?string $model = OpticalLensRxLensType::class;

    protected static ?string $modelLabel = 'extra customization (Rx)';

    protected static ?string $pluralModelLabel = 'Extra customizations (Rx)';

    protected static ?string $navigationLabel = 'Extra customizations (Rx)';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string|\UnitEnum|null $navigationGroup = 'Optical';

    protected static ?int $navigationSort = 17;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
                Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOpticalLensRxLensTypes::route('/'),
        ];
    }
}
