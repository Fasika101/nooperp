<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TelegramChatResource\Pages;
use App\Filament\Resources\TelegramChatResource\RelationManagers\TelegramMessagesRelationManager;
use App\Models\TelegramChat;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TelegramChatResource extends Resource
{
    protected static ?string $model = TelegramChat::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Telegram chats';

    protected static ?string $modelLabel = 'Telegram chat';

    protected static ?string $pluralModelLabel = 'Telegram chats';

    protected static ?int $navigationSort = 0;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('telegram_peer_id')
                    ->label('Peer ID')
                    ->disabled(),
                TextInput::make('type')
                    ->disabled(),
                TextInput::make('title')
                    ->disabled(),
                TextInput::make('username')
                    ->disabled(),
                TextInput::make('message_count')
                    ->label('Messages')
                    ->disabled(),
                TextInput::make('last_message_at')
                    ->label('Last message')
                    ->disabled(),
                TextInput::make('imported_at')
                    ->disabled(),
                Textarea::make('meta_display')
                    ->label('Meta (JSON)')
                    ->disabled()
                    ->dehydrated(false)
                    ->rows(6),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Title / name')
                    ->formatStateUsing(fn (TelegramChat $record): string => $record->display_title)
                    ->searchable(['title', 'username', 'telegram_peer_id'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('username')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('message_count')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('imported_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TelegramMessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTelegramChats::route('/'),
            'view' => Pages\ViewTelegramChat::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
