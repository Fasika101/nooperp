<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductDeletionLogResource\Pages;

use App\Filament\Resources\ProductDeletionLogResource;
use Filament\Resources\Pages\ListRecords;

class ListProductDeletionLogs extends ListRecords
{
    protected static string $resource = ProductDeletionLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
