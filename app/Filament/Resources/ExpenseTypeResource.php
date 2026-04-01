<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseTypeResource\Pages;
use App\Models\ExpenseType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ExpenseTypeResource extends Resource
{
    protected static ?string $model = ExpenseType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Expense Types';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Rent, Utilities, Supplies'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Only active expense types appear when adding expenses'),
                Toggle::make('is_recurring')
                    ->label('Recurring')
                    ->default(false)
                    ->helperText('Recurring expenses repeat (e.g. rent, utilities). One-time expenses are single occurrences (e.g. equipment).')
                    ->live(),
                Select::make('frequency')
                    ->label('Frequency')
                    ->options([
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly',
                        'yearly' => 'Yearly',
                    ])
                    ->required(fn ($get) => $get('is_recurring'))
                    ->visible(fn ($get) => $get('is_recurring'))
                    ->helperText('How often this expense typically repeats'),
                TextInput::make('day_of_month')
                    ->label('Day of month')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(31)
                    ->visible(fn ($get) => $get('is_recurring') && $get('frequency') === 'monthly')
                    ->helperText('Day of month this expense is usually due (1–31)'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_recurring')
                    ->label('Recurring')
                    ->boolean(),
                Tables\Columns\TextColumn::make('frequency')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst($state) : '—'),
            ])
            ->filters([
                //
            ])
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
            'index' => Pages\ListExpenseTypes::route('/'),
            'create' => Pages\CreateExpenseType::route('/create'),
            'edit' => Pages\EditExpenseType::route('/{record}/edit'),
        ];
    }
}
