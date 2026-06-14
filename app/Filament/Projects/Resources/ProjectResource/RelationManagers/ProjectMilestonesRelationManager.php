<?php

namespace App\Filament\Projects\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectMilestone;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectMilestonesRelationManager extends RelationManager
{
    protected static string $relationship = 'milestones';

    protected static ?string $title = 'Milestones';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')->required()->maxLength(255)->columnSpanFull(),
                Textarea::make('description')->rows(3)->columnSpanFull(),
                DatePicker::make('due_date')->label('Due Date'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->description(fn (ProjectMilestone $r) => $r->description ? str($r->description)->limit(60) : null),
                Tables\Columns\TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->placeholder('No date')
                    ->color(fn (ProjectMilestone $r): string => match (true) {
                        $r->completed_at !== null     => 'gray',
                        $r->due_date === null          => 'gray',
                        $r->due_date->isPast()         => 'danger',
                        $r->due_date->lte(now()->addDays(7)) => 'warning',
                        default                        => 'success',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (ProjectMilestone $r) => $r->isCompleted() ? 'Completed' : ($r->isOverdue() ? 'Overdue' : 'Pending'))
                    ->color(fn (string $state) => match ($state) {
                        'Completed' => 'success',
                        'Overdue'   => 'danger',
                        default     => 'gray',
                    }),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created by')
                    ->placeholder('—'),
            ])
            ->defaultSort('due_date')
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): ProjectMilestone {
                        $data['created_by'] = auth()->id();

                        return $this->getOwnerRecord()->milestones()->create($data);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle')
                    ->label(fn (ProjectMilestone $r) => $r->isCompleted() ? 'Reopen' : 'Mark Complete')
                    ->icon(fn (ProjectMilestone $r) => $r->isCompleted() ? 'heroicon-o-arrow-uturn-left' : 'heroicon-o-check-circle')
                    ->color(fn (ProjectMilestone $r) => $r->isCompleted() ? 'gray' : 'success')
                    ->action(fn (ProjectMilestone $r) => $r->update([
                        'completed_at' => $r->isCompleted() ? null : now(),
                    ])),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
