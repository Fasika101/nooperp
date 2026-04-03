<?php

namespace App\Filament\Resources\TelegramBotChatResource\Pages;

use App\Filament\Resources\TelegramBotChatResource;
use App\Models\TelegramBotChat;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class ViewTelegramBotChat extends ViewRecord
{
    protected static string $resource = TelegramBotChatResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var TelegramBotChat $model */
        $model = $this->getRecord();
        $model->update(['staff_last_read_at' => now()]);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->display_title;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getRelationManagersContentComponent(),
            ]);
    }
}
