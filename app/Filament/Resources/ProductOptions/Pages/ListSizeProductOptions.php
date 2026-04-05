<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions\Pages;

use App\Filament\Resources\ProductOptions\SizeProductOptionResource;

class ListSizeProductOptions extends ListProductOptionTypeRecords
{
    protected static string $resource = SizeProductOptionResource::class;
}
