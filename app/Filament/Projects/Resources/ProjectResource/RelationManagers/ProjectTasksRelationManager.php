<?php

namespace App\Filament\Projects\Resources\ProjectResource\RelationManagers;

use App\Filament\Projects\Resources\ProjectTaskResource;
use App\Models\ProjectLabel;
use App\Models\ProjectTask;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Tasks';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                Select::make('project_task_stage_id')
                    ->relationship('stage', 'name')
                    ->required()
                    ->preload()
                    ->searchable(),
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
                Toggle::make('is_starred')
                    ->label('⭐ Starred'),
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
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_starred')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->width('32px'),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->description(fn (ProjectTask $r) => $r->labels->pluck('name')->implode(', ') ?: null),
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
                        $record->due_date === null             => 'gray',
                        $record->due_date->isPast()            => 'danger',
                        $record->due_date->lte(now()->addDays(3)) => 'warning',
                        default                               => 'success',
                    }),
                Tables\Columns\TextColumn::make('assignees.name')
                    ->label('Assignees')
                    ->badge()
                    ->separator(','),
            ])
            ->defaultSort('is_starred', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): ProjectTask {
                        $assigneeIds = $data['assignee_ids'] ?? [];
                        $labelIds    = $data['label_ids'] ?? [];
                        unset($data['assignee_ids'], $data['label_ids']);
                        $data['created_by'] = auth()->id();
                        $task = $this->getOwnerRecord()->tasks()->create($data);
                        $task->assignees()->sync($assigneeIds);
                        $task->labels()->sync($labelIds);

                        return $task;
                    }),
            ])
            ->actions([
                Action::make('open')
                    ->label('Details')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (ProjectTask $record) => ProjectTaskResource::getUrl('edit', ['record' => $record])),
                EditAction::make()
                    ->fillForm(function (ProjectTask $record): array {
                        return [
                            'title'                => $record->title,
                            'description'          => $record->description,
                            'project_task_stage_id' => $record->project_task_stage_id,
                            'priority'             => $record->priority,
                            'due_date'             => $record->due_date,
                            'estimated_hours'      => $record->estimated_hours,
                            'progress'             => $record->progress,
                            'is_starred'           => $record->is_starred,
                            'assignee_ids'         => $record->assignees->pluck('id')->all(),
                            'label_ids'            => $record->labels->pluck('id')->all(),
                        ];
                    })
                    ->using(function (ProjectTask $record, array $data): ProjectTask {
                        $assigneeIds = $data['assignee_ids'] ?? [];
                        $labelIds    = $data['label_ids'] ?? [];
                        unset($data['assignee_ids'], $data['label_ids']);
                        $record->update($data);
                        $record->assignees()->sync($assigneeIds);
                        $record->labels()->sync($labelIds);

                        return $record;
                    }),
                DeleteAction::make(),
            ]);
    }
}
