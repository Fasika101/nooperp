<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions\Pages;

use App\Filament\Resources\ProductOptions\ShapeProductOptionResource;

class ListShapeProductOptions extends ListProductOptionTypeRecords
{
    protected static string $resource = ShapeProductOptionResource::class;
}
