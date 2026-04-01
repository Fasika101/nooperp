<?php

namespace App\Filament\Resources\OpticalLensNoPrescriptions;

use App\Filament\Resources\OpticalLensNoPrescriptions\Pages\ManageOpticalLensNoPrescriptions;
use App\Models\OpticalLensNoPrescription;
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

class OpticalLensNoPrescriptionResource extends Resource
{
    protected static ?string $model = OpticalLensNoPrescription::class;

    protected static ?string $modelLabel = 'lens (no prescription)';

    protected static ?string $pluralModelLabel = 'Lenses (no prescription)';

    protected static ?string $navigationLabel = 'Lenses (no Rx)';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sun';

    protected static string|\UnitEnum|null $navigationGroup = 'Optical';

    protected static ?int $navigationSort = 15;

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                TextInput::make('price')
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
                Tables\Columns\TextColumn::make('price')
                    ->money($currency)
                    ->sortable(),
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
            'index' => ManageOpticalLensNoPrescriptions::route('/'),
        ];
    }
}
