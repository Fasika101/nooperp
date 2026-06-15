<?php

namespace App\Filament\Projects\Resources;

use App\Filament\Projects\Resources\ProjectTaskResource\Pages;
use App\Filament\Projects\Resources\ProjectTaskResource\RelationManagers\TaskCommentsRelationManager;
use App\Filament\Projects\Resources\ProjectTaskResource\RelationManagers\TaskTimeLogsRelationManager;
use App\Models\Project;
use App\Models\ProjectLabel;
use App\Models\ProjectTask;
use App\Models\ProjectTaskStage;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectTaskResource extends Resource
{
    protected static ?string $model = ProjectTask::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Management';

    protected static ?string $navigationLabel = 'All Tasks';

    protected static ?int $navigationSort = 35;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // Left: main task info
                Grid::make(2)
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('project_id')
                            ->label('Project')
                            ->options(fn () => Project::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpanFull(),

                        Select::make('project_task_stage_id')
                            ->label('Stage')
                            ->options(fn () => ProjectTaskStage::orderBy('position')->pluck('name', 'id'))
                            ->required()
                            ->preload(),

                        Select::make('priority')
                            ->options([
                                ProjectTask::PRIORITY_LOW    => 'Low',
                                ProjectTask::PRIORITY_NORMAL => 'Normal',
                                ProjectTask::PRIORITY_HIGH   => 'High',
                                ProjectTask::PRIORITY_URGENT => 'Urgent',
                            ])
                            ->default(ProjectTask::PRIORITY_NORMAL)
                            ->required(),

                        DatePicker::make('due_date'),

                        TextInput::make('estimated_hours')
                            ->label('Est. hours')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.5)
                            ->suffix('h'),

                        TextInput::make('progress')
                            ->label('Progress %')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(0),

                        Select::make('assignee_ids')
                            ->label('Assignees')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->columnSpanFull(),

                        Select::make('label_ids')
                            ->label('Labels')
                            ->multiple()
                            ->preload()
                            ->options(fn () => ProjectLabel::orderBy('name')->pluck('name', 'id'))
                            ->columnSpanFull(),

                        Toggle::make('is_starred')
                            ->label('⭐ Starred / Important')
                            ->columnSpanFull(),

                        // Checklist repeater
                        Repeater::make('checklistItems')
                            ->label('Checklist')
                            ->relationship('checklists')
                            ->orderColumn('position')
                            ->collapsible()
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->placeholder('Checklist item…')
                                    ->columnSpan(5),
                                Toggle::make('is_done')
                                    ->label('Done')
                                    ->columnSpan(1),
                            ])
                            ->columns(6)
                            ->addActionLabel('Add checklist item')
                            ->defaultItems(0),
                    ]),

                // Right: summary sidebar
                Section::make('Summary')
                    ->columnSpan(1)
                    ->schema([
                        Placeholder::make('logged_hours')
                            ->label('Hours logged')
                            ->content(function (?ProjectTask $record): string {
                                if (! $record) {
                                    return '—';
                                }
                                $total = $record->totalLoggedHours();
                                $est   = $record->estimated_hours;

                                return $est
                                    ? "{$total}h / {$est}h"
                                    : "{$total}h";
                            }),

                        Placeholder::make('checklist_progress')
                            ->label('Checklist')
                            ->content(function (?ProjectTask $record): string {
                                if (! $record) {
                                    return '—';
                                }
                                $p = $record->checklistProgress();

                                return "{$p['done']} / {$p['total']} done";
                            }),

                        Placeholder::make('created_by_name')
                            ->label('Created by')
                            ->content(fn (?ProjectTask $record) => $record?->creator?->name ?? '—'),

                        Placeholder::make('created_at_fmt')
                            ->label('Created')
                            ->content(fn (?ProjectTask $record) => $record?->created_at?->diffForHumans() ?? '—'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_starred')
                    ->label('⭐')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable()
                    ->width('40px'),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->description(fn (ProjectTask $r) => $r->project?->name),

                Tables\Columns\TextColumn::make('stage.name')->badge(),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'urgent' => 'danger',
                        'high'   => 'warning',
                        'normal' => 'info',
                        default  => 'gray',
                    }),

                Tables\Columns\TextColumn::make('progress')
                    ->label('Progress')
                    ->formatStateUsing(fn (int $state): string => "{$state}%")
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 100 => 'success',
                        $state >= 50   => 'info',
                        $state > 0     => 'warning',
                        default        => 'gray',
                    }),

                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->placeholder('—')
                    ->color(fn (ProjectTask $record): string => match (true) {
                        $record->due_date === null    => 'gray',
                        $record->due_date->isPast()   => 'danger',
                        $record->due_date->lte(now()->addDays(3)) => 'warning',
                        default                       => 'success',
                    }),

                Tables\Columns\TextColumn::make('assignees.name')
                    ->label('Assignees')
                    ->badge()
                    ->separator(','),
            ])
            ->defaultSort('is_starred', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('project_id')
                    ->label('Project')
                    ->options(fn () => Project::orderBy('name')->pluck('name', 'id')),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        ProjectTask::PRIORITY_LOW    => 'Low',
                        ProjectTask::PRIORITY_NORMAL => 'Normal',
                        ProjectTask::PRIORITY_HIGH   => 'High',
                        ProjectTask::PRIORITY_URGENT => 'Urgent',
                    ]),
                Tables\Filters\TernaryFilter::make('is_starred')
                    ->label('Starred only'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TaskCommentsRelationManager::class,
            TaskTimeLogsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProjectTasks::route('/'),
            'edit'  => Pages\EditProjectTask::route('/{record}/edit'),
        ];
    }

}
