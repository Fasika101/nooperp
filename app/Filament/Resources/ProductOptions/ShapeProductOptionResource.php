<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions;

use App\Models\ProductOption;

class ShapeProductOptionResource extends ProductOptionTypeResource
{
    protected static ?string $slug = 'shapes';

    public static function optionType(): string
    {
        return ProductOption::TYPE_SHAPE;
    }

    public static function typeNavigationSort(): int
    {
        return 6;
    }

    protected static function getListPage(): string
    {
        return Pages\ListShapeProductOptions::class;
    }

    protected static function getCreatePage(): string
    {
        return Pages\CreateShapeProductOption::class;
    }

    protected static function getEditPage(): string
    {
        return Pages\EditShapeProductOption::class;
    }
}
