<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions;

use App\Models\ProductOption;

class SizeProductOptionResource extends ProductOptionTypeResource
{
    protected static ?string $slug = 'sizes';

    public static function optionType(): string
    {
        return ProductOption::TYPE_SIZE;
    }

    public static function typeNavigationSort(): int
    {
        return 2;
    }

    protected static function getListPage(): string
    {
        return Pages\ListSizeProductOptions::class;
    }

    protected static function getCreatePage(): string
    {
        return Pages\CreateSizeProductOption::class;
    }

    protected static function getEditPage(): string
    {
        return Pages\EditSizeProductOption::class;
    }
}
