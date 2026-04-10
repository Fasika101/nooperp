<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\SalaryTaxBracketResource\Pages;
use App\Models\SalaryTaxBracket;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SalaryTaxBracketResource extends Resource
{
    protected static ?string $model = SalaryTaxBracket::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Salary tax brackets';

    protected static ?string $modelLabel = 'Salary tax bracket';

    protected static ?string $pluralModelLabel = 'Salary tax brackets';

    protected static ?int $navigationSort = 26;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Bracket')
                    ->schema([
                        TextInput::make('from_amount')
                            ->label('From (gross salary)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        TextInput::make('to_amount')
                            ->label('To (leave empty for no upper limit)')
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('rate_percent')
                            ->label('Marginal rate')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->maxValue(100)
                            ->required(),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active brackets are used for employee payroll estimates.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('from_amount')
                    ->label('From')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                Tables\Columns\TextColumn::make('to_amount')
                    ->label('To')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('∞')
                    ->sortable(),
                Tables\Columns\TextColumn::make('rate_percent')
                    ->label('Rate')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->sortable(),
            ])
            ->defaultSort('sort_order')
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
            'index' => Pages\ListSalaryTaxBrackets::route('/'),
            'create' => Pages\CreateSalaryTaxBracket::route('/create'),
            'edit' => Pages\EditSalaryTaxBracket::route('/{record}/edit'),
        ];
    }
}
