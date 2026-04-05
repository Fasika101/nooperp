<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions\Pages;

use App\Filament\Resources\ProductOptions\ColorProductOptionResource;

class CreateColorProductOption extends CreateProductOptionTypeRecord
{
    protected static string $resource = ColorProductOptionResource::class;
}
