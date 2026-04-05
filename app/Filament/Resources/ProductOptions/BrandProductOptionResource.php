<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions;

use App\Models\ProductOption;

class BrandProductOptionResource extends ProductOptionTypeResource
{
    protected static ?string $slug = 'brands';

    public static function optionType(): string
    {
        return ProductOption::TYPE_BRAND;
    }

    public static function typeNavigationSort(): int
    {
        return 1;
    }

    protected static function getListPage(): string
    {
        return Pages\ListBrandProductOptions::class;
    }

    protected static function getCreatePage(): string
    {
        return Pages\CreateBrandProductOption::class;
    }

    protected static function getEditPage(): string
    {
        return Pages\EditBrandProductOption::class;
    }
}
