<?php

declare(strict_types=1);

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class ProductOptionsCluster extends Cluster
{
    protected static ?string $slug = 'product-options';

    protected static ?string $navigationLabel = 'Product options';

    protected static ?string $title = 'Product options';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-swatch';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?int $navigationSort = 4;
}
