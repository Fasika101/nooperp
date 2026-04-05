<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions\Pages;

use App\Filament\Resources\ProductOptions\GenderProductOptionResource;

class EditGenderProductOption extends EditProductOptionTypeRecord
{
    protected static string $resource = GenderProductOptionResource::class;
}
