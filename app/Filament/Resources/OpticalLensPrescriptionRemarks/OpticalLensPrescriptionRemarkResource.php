<?php

namespace App\Filament\Resources\OpticalLensPrescriptionRemarks;

use App\Filament\Resources\OpticalLensPrescriptionRemarks\Pages\ManageOpticalLensPrescriptionRemarks;
use App\Models\OpticalLensPrescriptionRemark;
use App\Models\Setting;
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

class OpticalLensPrescriptionRemarkResource extends Resource
{
    protected static ?string $model = OpticalLensPrescriptionRemark::class;

    protected static ?string $modelLabel = 'lens remark (with Rx)';

    protected static ?string $pluralModelLabel = 'Lens remarks (with Rx)';

    protected static ?string $navigationLabel = 'Lens remarks (Rx)';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Optical';

    protected static ?int $navigationSort = 16;

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('price_single_vision')
                    ->label('Price — single vision')
                    ->required()
                    ->numeric()
                    ->prefix($currency)
                    ->minValue(0),
                TextInput::make('price_progressive')
                    ->label('Price — progressive')
                    ->required()
                    ->numeric()
                    ->prefix($currency)
                    ->minValue(0),
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
        $currency = Setting::getDefaultCurrency();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price_single_vision')
                    ->label('SV')
                    ->money($currency),
                Tables\Columns\TextColumn::make('price_progressive')
                    ->label('Prog.')
                    ->money($currency),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                //
            ])
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
            'index' => ManageOpticalLensPrescriptionRemarks::route('/'),
        ];
    }
}
