<?php

namespace App\Filament\Resources\TelegramBotChatResource\Pages;

use App\Filament\Resources\TelegramBotChatResource;
use Filament\Resources\Pages\ListRecords;

class ListTelegramBotChats extends ListRecords
{
    protected static string $resource = TelegramBotChatResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
