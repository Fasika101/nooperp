<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectLabelResource\Pages;
use App\Models\ProjectLabel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectLabelResource extends Resource
{
    protected static ?string $model = ProjectLabel::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Task Labels';

    protected static ?int $navigationSort = 13;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(80),
                Select::make('color')
                    ->options([
                        'blue'   => 'Blue',
                        'green'  => 'Green',
                        'red'    => 'Red',
                        'yellow' => 'Yellow',
                        'purple' => 'Purple',
                        'pink'   => 'Pink',
                        'orange' => 'Orange',
                        'gray'   => 'Gray',
                    ])
                    ->default('blue')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\BadgeColumn::make('color')
                    ->colors([
                        'primary' => 'blue',
                        'success' => 'green',
                        'danger'  => 'red',
                        'warning' => 'yellow',
                        'info'    => 'purple',
                        'gray'    => static fn ($state) => in_array($state, ['gray', 'pink', 'orange']),
                    ]),
                Tables\Columns\TextColumn::make('tasks_count')
                    ->label('Tasks using')
                    ->counts('tasks'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageProjectLabels::route('/'),
        ];
    }
}
