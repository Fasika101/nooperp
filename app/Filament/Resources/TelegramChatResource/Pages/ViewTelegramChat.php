<?php

namespace App\Filament\Resources\TelegramChatResource\Pages;

use App\Filament\Resources\TelegramChatResource;
use App\Models\TelegramChat;
use Filament\Resources\Pages\ViewRecord;

class ViewTelegramChat extends ViewRecord
{
    protected static string $resource = TelegramChatResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        /** @var TelegramChat $record */
        $record = $this->getRecord();
        $meta = $record->meta;
        $data['meta_display'] = $meta
            ? json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';

        return $data;
    }
}
