<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProjectResource\Pages;
use App\Filament\Resources\ProjectResource\RelationManagers\ProjectTasksRelationManager;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static string|\UnitEnum|null $navigationGroup = 'Projects';

    protected static ?string $navigationLabel = 'Projects';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->preload()
                    ->searchable(),
                Select::make('status')
                    ->options([
                        Project::STATUS_DRAFT => 'Draft',
                        Project::STATUS_ACTIVE => 'Active',
                        Project::STATUS_ON_HOLD => 'On hold',
                        Project::STATUS_COMPLETED => 'Completed',
                        Project::STATUS_CANCELLED => 'Cancelled',
                    ])
                    ->required()
                    ->default(Project::STATUS_ACTIVE),
                DatePicker::make('start_date'),
                DatePicker::make('end_date'),
                Select::make('member_ids')
                    ->label('Team members')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                    ->helperText('Creator is always kept on the team when you save.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('creator.name')->label('Created by')->placeholder('—'),
                Tables\Columns\TextColumn::make('start_date')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('end_date')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProjectTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit' => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
