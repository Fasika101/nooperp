<?php

namespace App\Filament\Resources\TelegramChatResource\Pages;

use App\Filament\Resources\TelegramChatResource;
use Filament\Resources\Pages\ListRecords;

class ListTelegramChats extends ListRecords
{
    protected static string $resource = TelegramChatResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
