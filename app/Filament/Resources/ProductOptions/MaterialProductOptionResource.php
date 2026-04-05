<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions;

use App\Models\ProductOption;

class MaterialProductOptionResource extends ProductOptionTypeResource
{
    protected static ?string $slug = 'materials';

    public static function optionType(): string
    {
        return ProductOption::TYPE_MATERIAL;
    }

    public static function typeNavigationSort(): int
    {
        return 5;
    }

    protected static function getListPage(): string
    {
        return Pages\ListMaterialProductOptions::class;
    }

    protected static function getCreatePage(): string
    {
        return Pages\CreateMaterialProductOption::class;
    }

    protected static function getEditPage(): string
    {
        return Pages\EditMaterialProductOption::class;
    }
}
