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
use Livewire\WithFileUploads;
use Throwable;

class TelegramBotMessagesRelationManager extends RelationManager
{
    use WithFileUploads;

    protected static string $relationship = 'messages';

    protected static ?string $title = 'Conversation';

    public string $messageDraft = '';

    /** @var mixed */
    public $attachment = null;

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
        $chat = $this->getOwnerRecord();
        $bots = app(TelegramBotService::class);

        if ($this->attachment) {
            $this->validate([
                'attachment' => [
                    'required',
                    'file',
                    'max:51200',
                ],
                'messageDraft' => ['nullable', 'string', 'max:1024'],
            ]);

            $upload = $this->attachment;
            $path = method_exists($upload, 'getRealPath') ? $upload->getRealPath() : null;
            if (! is_string($path) || $path === '' || ! is_readable($path)) {
                $path = method_exists($upload, 'path') ? $upload->path() : null;
            }
            $name = method_exists($upload, 'getClientOriginalName') ? $upload->getClientOriginalName() : 'upload';
            $caption = trim($this->messageDraft);

            try {
                if (! is_string($path) || $path === '' || ! is_readable($path)) {
                    throw new \RuntimeException('Could not read the uploaded file.');
                }

                $bots->sendAttachmentToChat(
                    $chat,
                    $path,
                    is_string($name) && $name !== '' ? $name : 'upload',
                    $caption !== '' ? $caption : null
                );
                $this->messageDraft = '';
                $this->attachment = null;
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

            return;
        }

        $this->validate([
            'messageDraft' => ['required', 'string', 'max:4096'],
        ]);

        $text = trim($this->messageDraft);

        try {
            $bots->sendTextToChat($chat, $text);
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
