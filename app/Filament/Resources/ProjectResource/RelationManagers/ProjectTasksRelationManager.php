<?php

namespace App\Filament\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectTask;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                TextInput::make('title')->required()->maxLength(255),
                Textarea::make('description')->rows(3),
                Select::make('project_task_stage_id')
                    ->relationship('stage', 'name')
                    ->required()
                    ->preload()
                    ->searchable(),
                Select::make('priority')
                    ->options([
                        ProjectTask::PRIORITY_LOW => 'Low',
                        ProjectTask::PRIORITY_NORMAL => 'Normal',
                        ProjectTask::PRIORITY_HIGH => 'High',
                        ProjectTask::PRIORITY_URGENT => 'Urgent',
                    ])
                    ->default(ProjectTask::PRIORITY_NORMAL)
                    ->required(),
                DatePicker::make('due_date'),
                Select::make('assignee_ids')
                    ->label('Assignees')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id')),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('stage.name')->badge(),
                Tables\Columns\TextColumn::make('priority')->badge(),
                Tables\Columns\TextColumn::make('due_date')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('assignees.name')
                    ->label('Assignees')
                    ->badge()
                    ->separator(','),
            ])
            ->defaultSort('due_date')
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): ProjectTask {
                        $assigneeIds = $data['assignee_ids'] ?? [];
                        unset($data['assignee_ids']);
                        $data['created_by'] = auth()->id();
                        $task = $this->getOwnerRecord()->tasks()->create($data);
                        $task->assignees()->sync($assigneeIds);

                        return $task;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->fillForm(function (ProjectTask $record): array {
                        return [
                            'title' => $record->title,
                            'description' => $record->description,
                            'project_task_stage_id' => $record->project_task_stage_id,
                            'priority' => $record->priority,
                            'due_date' => $record->due_date,
                            'assignee_ids' => $record->assignees->pluck('id')->all(),
                        ];
                    })
                    ->using(function (ProjectTask $record, array $data): ProjectTask {
                        $assigneeIds = $data['assignee_ids'] ?? [];
                        unset($data['assignee_ids']);
                        $record->update($data);
                        $record->assignees()->sync($assigneeIds);

                        return $record;
                    }),
                DeleteAction::make(),
            ]);
    }
}
