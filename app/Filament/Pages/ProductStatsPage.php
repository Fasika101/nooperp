<?php

namespace App\Filament\Pages;

use App\Services\ProductStatsService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

class ProductStatsPage extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|\UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $navigationLabel = 'Product stats';

    protected static ?string $title = 'Product stats';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.product-stats-page';

    #[Url(as: 'branch')]
    public string $branchFilter = 'all';

    /** @var array<string, string> */
    public array $branchOptions = [];

    /** @var array<string, mixed> */
    public array $report = [];

    public function mount(ProductStatsService $stats): void
    {
        $this->branchOptions = $stats->branchFilterOptions();

        if ($this->branchOptions === []) {
            $this->branchFilter = 'all';
            $this->refreshReport($stats);

            return;
        }

        if (! array_key_exists($this->branchFilter, $this->branchOptions)) {
            $this->branchFilter = $stats->defaultBranchFilter();
        }

        if (! array_key_exists($this->branchFilter, $this->branchOptions)) {
            $this->branchFilter = (string) array_key_first($this->branchOptions);
        }

        $this->refreshReport($stats);
    }

    public function updatedBranchFilter(ProductStatsService $stats): void
    {
        $this->refreshReport($stats);
    }

    protected function refreshReport(ProductStatsService $stats): void
    {
        $this->report = $stats->buildReport($this->branchFilter);
    }
}
