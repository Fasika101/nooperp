<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JobPositionResource\Pages;
use App\Models\JobPosition;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class JobPositionResource extends Resource
{
    protected static ?string $model = JobPosition::class;

    protected static ?string $modelLabel = 'job position';

    protected static ?string $pluralModelLabel = 'Job positions';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'HR';

    protected static ?int $navigationSort = 22;

    protected static ?string $navigationLabel = 'Job positions';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(2)
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
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('department.name')->label('Department')->placeholder('—'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('sort_order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Department'),
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobPositions::route('/'),
            'create' => Pages\CreateJobPosition::route('/create'),
            'edit' => Pages\EditJobPosition::route('/{record}/edit'),
        ];
    }
}
