<?php

namespace App\Filament\Resources\TelegramBotChatResource\RelationManagers;

use App\Models\TelegramBotMessage;
use App\Services\TelegramBotService;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Throwable;

class TelegramBotMessagesRelationManager extends RelationManager
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
                Tables\Columns\TextColumn::make('direction')
                    ->badge()
                    ->color(fn (string $state): string => $state === TelegramBotMessage::DIRECTION_OUTGOING ? 'info' : 'success'),
                Tables\Columns\TextColumn::make('text')
                    ->limit(200)
                    ->wrap(),
            ])
            ->defaultSort('sent_at', 'desc')
            ->headerActions([
                CreateAction::make('send_message')
                    ->label('Send message')
                    ->icon('heroicon-o-paper-airplane')
                    ->form([
                        Textarea::make('text')
                            ->label('Message')
                            ->required()
                            ->rows(4)
                            ->maxLength(4096),
                    ])
                    ->action(function (array $data): void {
                        $chat = $this->getOwnerRecord();
                        try {
                            app(TelegramBotService::class)->sendTextToChat($chat, $data['text']);
                            Notification::make()
                                ->success()
                                ->title('Message sent')
                                ->send();
                        } catch (Throwable $e) {
                            Notification::make()
                                ->danger()
                                ->title('Failed to send')
                                ->body($e->getMessage())
                                ->send();
                        }
                    }),
            ])
            ->actions([
                //
            ])
            ->bulkActions([
                //
            ]);
    }
}
