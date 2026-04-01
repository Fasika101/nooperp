<?php

namespace App\Filament\Resources\TelegramChatResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TelegramMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Messages';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('telegram_message_id')
            ->columns([
                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_outgoing')
                    ->label('Out')
                    ->boolean(),
                Tables\Columns\TextColumn::make('sender_name')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sender_peer_id')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('text')
                    ->limit(200)
                    ->searchable(),
            ])
            ->defaultSort('sent_at', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}
