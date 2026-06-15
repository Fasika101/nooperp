<?php

namespace App\Filament\Projects\Resources\ProjectTaskResource\RelationManagers;

use App\Models\ProjectTimeLog;
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

class TaskTimeLogsRelationManager extends RelationManager
{
    protected static string $relationship = 'timeLogs';

    protected static ?string $title = 'Time Logs';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('hours')
                    ->label('Hours')
                    ->numeric()
                    ->minValue(0.25)
                    ->step(0.25)
                    ->suffix('h')
                    ->required(),

                DatePicker::make('logged_on')
                    ->label('Date')
                    ->default(now())
                    ->required(),

                Textarea::make('note')
                    ->label('What did you work on?')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Employee')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('hours')
                    ->label('Hours')
                    ->formatStateUsing(fn ($state) => "{$state}h")
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('logged_on')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->limit(80)
                    ->placeholder('—'),
            ])
            ->defaultSort('logged_on', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Log Time')
                    ->using(function (array $data): ProjectTimeLog {
                        $task           = $this->getOwnerRecord();
                        $data['user_id']   = auth()->id();
                        $data['project_id'] = $task->project_id;

                        return $task->timeLogs()->create($data);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (ProjectTimeLog $r) => (int) $r->user_id === (int) auth()->id()),
                DeleteAction::make()
                    ->visible(fn (ProjectTimeLog $r) => (int) $r->user_id === (int) auth()->id()),
            ])
            ->description(fn () => 'Total logged: ' . $this->getOwnerRecord()->totalLoggedHours() . 'h');
    }
}
