<?php

namespace App\Filament\Projects\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectBug;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectBugsRelationManager extends RelationManager
{
    protected static string $relationship = 'bugs';

    protected static ?string $title = 'Bugs';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                Select::make('priority')
                    ->options([
                        ProjectBug::PRIORITY_LOW      => 'Low',
                        ProjectBug::PRIORITY_NORMAL   => 'Normal',
                        ProjectBug::PRIORITY_HIGH     => 'High',
                        ProjectBug::PRIORITY_CRITICAL => 'Critical',
                    ])
                    ->default(ProjectBug::PRIORITY_NORMAL)
                    ->required(),
                Select::make('status')
                    ->options([
                        ProjectBug::STATUS_OPEN        => 'Open',
                        ProjectBug::STATUS_IN_PROGRESS => 'In Progress',
                        ProjectBug::STATUS_RESOLVED    => 'Resolved',
                        ProjectBug::STATUS_CLOSED      => 'Closed',
                    ])
                    ->default(ProjectBug::STATUS_OPEN)
                    ->required(),
                Select::make('assigned_user_id')
                    ->label('Assigned To')
                    ->options(fn () => User::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->description(fn (ProjectBug $r) => $r->description ? str($r->description)->limit(60) : null),
                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'critical' => 'danger',
                        'high'     => 'warning',
                        'normal'   => 'info',
                        default    => 'gray',
                    }),
                Tables\Columns\SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        ProjectBug::STATUS_OPEN        => 'Open',
                        ProjectBug::STATUS_IN_PROGRESS => 'In Progress',
                        ProjectBug::STATUS_RESOLVED    => 'Resolved',
                        ProjectBug::STATUS_CLOSED      => 'Closed',
                    ]),
                Tables\Columns\TextColumn::make('assignedUser.name')
                    ->label('Assigned to')
                    ->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Reported by')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Reported')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): ProjectBug {
                        $data['created_by'] = auth()->id();

                        return $this->getOwnerRecord()->bugs()->create($data);
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        ProjectBug::STATUS_OPEN        => 'Open',
                        ProjectBug::STATUS_IN_PROGRESS => 'In Progress',
                        ProjectBug::STATUS_RESOLVED    => 'Resolved',
                        ProjectBug::STATUS_CLOSED      => 'Closed',
                    ]),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        ProjectBug::PRIORITY_LOW      => 'Low',
                        ProjectBug::PRIORITY_NORMAL   => 'Normal',
                        ProjectBug::PRIORITY_HIGH     => 'High',
                        ProjectBug::PRIORITY_CRITICAL => 'Critical',
                    ]),
            ]);
    }
}
