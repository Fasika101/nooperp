<?php

namespace App\Filament\Projects\Resources\ProjectTaskResource\RelationManagers;

use App\Models\ProjectTaskComment;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TaskCommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Discussion';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('body')
                    ->label('Comment')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('body')
                    ->label('Comment')
                    ->limit(140)
                    ->wrap(),
                Tables\Columns\TextColumn::make('author.name')
                    ->label('Author')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Posted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Add Comment')
                    ->using(function (array $data): ProjectTaskComment {
                        $data['created_by'] = auth()->id();

                        return $this->getOwnerRecord()->comments()->create($data);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (ProjectTaskComment $r) => (int) $r->created_by === (int) auth()->id()),
                DeleteAction::make()
                    ->visible(fn (ProjectTaskComment $r) => (int) $r->created_by === (int) auth()->id()),
            ]);
    }
}
