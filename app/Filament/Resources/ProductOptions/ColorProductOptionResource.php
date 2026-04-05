<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions;

use App\Models\ProductOption;

class ColorProductOptionResource extends ProductOptionTypeResource
{
    protected static ?string $slug = 'colors';

    public static function optionType(): string
    {
        return ProductOption::TYPE_COLOR;
    }

    public static function typeNavigationSort(): int
    {
        return 3;
    }

    protected static function getListPage(): string
    {
        return Pages\ListColorProductOptions::class;
    }

    protected static function getCreatePage(): string
    {
        return Pages\CreateColorProductOption::class;
    }

    protected static function getEditPage(): string
    {
        return Pages\EditColorProductOption::class;
    }
}
