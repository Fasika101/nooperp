<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions\Pages;

use App\Filament\Resources\ProductOptions\MaterialProductOptionResource;

class ListMaterialProductOptions extends ListProductOptionTypeRecords
{
    protected static string $resource = MaterialProductOptionResource::class;
}
