<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions;

use App\Models\ProductOption;

class GenderProductOptionResource extends ProductOptionTypeResource
{
    protected static ?string $slug = 'gender';

    public static function optionType(): string
    {
        return ProductOption::TYPE_GENDER;
    }

    public static function typeNavigationSort(): int
    {
        return 4;
    }

    protected static function getListPage(): string
    {
        return Pages\ListGenderProductOptions::class;
    }

    protected static function getCreatePage(): string
    {
        return Pages\CreateGenderProductOption::class;
    }

    protected static function getEditPage(): string
    {
        return Pages\EditGenderProductOption::class;
    }
}
