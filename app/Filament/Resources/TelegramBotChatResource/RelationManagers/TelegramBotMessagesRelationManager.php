<?php

namespace App\Filament\Resources\TelegramBotChatResource\RelationManagers;

use App\Services\TelegramBotService;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Throwable;

class TelegramBotMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Conversation';

    public string $messageDraft = '';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                RenderHook::make(PanelsRenderHook::RESOURCE_RELATION_MANAGER_BEFORE),
                SchemaView::make('filament.resources.telegram-bot-chats.chat-thread'),
                RenderHook::make(PanelsRenderHook::RESOURCE_RELATION_MANAGER_AFTER),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('telegram_message_id')
            ->columns([
                Tables\Columns\TextColumn::make('text')
                    ->label('')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sent_at', 'desc')
            ->paginated(false);
    }

    public function sendChatMessage(): void
    {
        $this->validate([
            'messageDraft' => ['required', 'string', 'max:4096'],
        ]);

        $chat = $this->getOwnerRecord();
        $text = trim($this->messageDraft);

        try {
            app(TelegramBotService::class)->sendTextToChat($chat, $text);
            $this->messageDraft = '';
            $this->js('setTimeout(() => { const el = document.querySelector("[data-telegram-thread]"); if (el) el.scrollTop = el.scrollHeight; }, 75);');
            Notification::make()
                ->success()
                ->title(__('Message sent'))
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->danger()
                ->title(__('Failed to send'))
                ->body($e->getMessage())
                ->send();
        }
    }
}
