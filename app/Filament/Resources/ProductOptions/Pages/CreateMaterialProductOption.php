<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions\Pages;

use App\Filament\Resources\ProductOptions\MaterialProductOptionResource;

class CreateMaterialProductOption extends CreateProductOptionTypeRecord
{
    protected static string $resource = MaterialProductOptionResource::class;
}
