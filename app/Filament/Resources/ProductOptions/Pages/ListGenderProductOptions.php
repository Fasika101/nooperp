<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions\Pages;

use App\Filament\Resources\ProductOptions\GenderProductOptionResource;

class ListGenderProductOptions extends ListProductOptionTypeRecords
{
    protected static string $resource = GenderProductOptionResource::class;
}
