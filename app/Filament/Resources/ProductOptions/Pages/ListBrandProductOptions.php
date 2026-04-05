<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions\Pages;

use App\Filament\Resources\ProductOptions\BrandProductOptionResource;

class ListBrandProductOptions extends ListProductOptionTypeRecords
{
    protected static string $resource = BrandProductOptionResource::class;
}
