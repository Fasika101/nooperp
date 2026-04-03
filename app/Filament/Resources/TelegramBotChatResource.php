<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TelegramBotChatResource\Pages;
use App\Filament\Resources\TelegramBotChatResource\RelationManagers\TelegramBotMessagesRelationManager;
use App\Models\Customer;
use App\Models\TelegramBotChat;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TelegramBotChatResource extends Resource
{
    protected static ?string $model = TelegramBotChat::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Telegram bot chats';

    protected static ?string $modelLabel = 'Telegram bot chat';

    protected static ?string $pluralModelLabel = 'Telegram bot chats';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('telegram_chat_id')
                    ->label('Telegram chat ID')
                    ->disabled(),
                TextInput::make('type')
                    ->disabled(),
                TextInput::make('title')
                    ->disabled(),
                TextInput::make('username')
                    ->disabled(),
                TextInput::make('first_name')
                    ->disabled(),
                TextInput::make('last_name')
                    ->disabled(),
                TextInput::make('message_count')
                    ->label('Messages')
                    ->disabled(),
                TextInput::make('last_message_at')
                    ->label('Last message')
                    ->disabled(),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = TelegramBotChat::query()->unreadByStaff()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Telegram chats with new customer messages';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('customer'))
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Chat')
                    ->getStateUsing(fn (TelegramBotChat $record): string => $record->display_title)
                    ->searchable(['title', 'username', 'first_name', 'telegram_chat_id']),
                Tables\Columns\TextColumn::make('type')
                    ->badge(),
                Tables\Columns\TextColumn::make('username')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('message_count')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_message_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Customer')
                    ->placeholder('—')
                    ->url(fn (TelegramBotChat $record): ?string => $record->customer instanceof Customer
                        ? CustomerResource::getUrl('edit', ['record' => $record->customer])
                        : null),
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
            TelegramBotMessagesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTelegramBotChats::route('/'),
            'view' => Pages\ViewTelegramBotChat::route('/{record}'),
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
