<?php

declare(strict_types=1);

namespace App\Filament\Resources\OrderDeletionLogResource\Pages;

use App\Filament\Resources\OrderDeletionLogResource;
use Filament\Resources\Pages\ListRecords;

class ListOrderDeletionLogs extends ListRecords
{
    protected static string $resource = OrderDeletionLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
