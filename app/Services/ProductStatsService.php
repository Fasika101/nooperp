<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductStatsService
{
    /**
     * @return array<string, string>
     */
    public function branchFilterOptions(?User $user = null): array
    {
        $user ??= auth()->user();

        $branches = Branch::query()
            ->where('is_active', true)
            ->when(
                $user?->isBranchRestricted(),
                fn ($query) => $query->whereIn('id', $user->branchIds()),
            )
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        if ($branches === []) {
            return [];
        }

        if ($user?->isBranchRestricted()) {
            if (count($branches) > 1) {
                return ['all' => 'All assigned branches'] + $branches;
            }

            return $branches;
        }

        return ['all' => 'All branches'] + $branches;
    }

    public function defaultBranchFilter(?User $user = null): string
    {
        $user ??= auth()->user();
        $options = $this->branchFilterOptions($user);

        if ($options === []) {
            return 'all';
        }

        if ($user?->isBranchRestricted() && count($options) === 1) {
            return (string) array_key_first($options);
        }

        return 'all';
    }

    /**
     * @return list<int>
     */
    public function resolveBranchIds(string $branchFilter, ?User $user = null): array
    {
        $user ??= auth()->user();

        if ($user?->isBranchRestricted()) {
            $allowed = $user->branchIds();

            if ($allowed === []) {
                return [];
            }

            if ($branchFilter !== 'all' && in_array((int) $branchFilter, $allowed, true)) {
                return [(int) $branchFilter];
            }

            return $allowed;
        }

        if ($branchFilter !== 'all') {
            return [(int) $branchFilter];
        }

        return Branch::query()
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{unique_products: int, total_quantity: int}
     */
    public function inventoryStats(array $branchIds): array
    {
        if ($branchIds === []) {
            return [
                'unique_products' => 0,
                'total_quantity' => 0,
            ];
        }

        $stockQuery = BranchProductStock::query()
            ->whereIn('branch_id', $branchIds)
            ->whereHas('productVariant.product', fn ($query) => $query->where('is_service', false));

        $uniqueProducts = Product::query()
            ->where('is_service', false)
            ->whereHas(
                'branchStocks',
                fn ($query) => $query->whereIn('branch_id', $branchIds)->where('quantity', '>', 0),
            )
            ->count();

        $totalQuantity = (int) (clone $stockQuery)->sum('quantity');

        return [
            'unique_products' => $uniqueProducts,
            'total_quantity' => $totalQuantity,
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return array{units_sold: int, revenue: float}
     */
    public function salesSummaryForMonth(array $branchIds, ?Carbon $month = null): array
    {
        $month ??= now();

        if ($branchIds === []) {
            return [
                'units_sold' => 0,
                'revenue' => 0.0,
            ];
        }

        $row = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.status', 'completed')
            ->where('products.is_service', false)
            ->whereIn('orders.branch_id', $branchIds)
            ->whereMonth('orders.created_at', $month->month)
            ->whereYear('orders.created_at', $month->year)
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0) as units_sold')
            ->selectRaw('COALESCE(SUM(order_items.quantity * order_items.price), 0) as revenue')
            ->first();

        return [
            'units_sold' => (int) ($row->units_sold ?? 0),
            'revenue' => (float) ($row->revenue ?? 0),
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return Collection<int, object{
     *     product_id: int,
     *     product_name: string,
     *     units_sold: int,
     *     revenue: float
     * }>
     */
    public function topSellingProductsForMonth(array $branchIds, int $limit = 10, ?Carbon $month = null): Collection
    {
        $month ??= now();

        if ($branchIds === []) {
            return collect();
        }

        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.status', 'completed')
            ->where('products.is_service', false)
            ->whereIn('orders.branch_id', $branchIds)
            ->whereMonth('orders.created_at', $month->month)
            ->whereYear('orders.created_at', $month->year)
            ->groupBy('order_items.product_id', 'products.name')
            ->orderByDesc(DB::raw('SUM(order_items.quantity)'))
            ->limit($limit)
            ->get([
                'order_items.product_id as product_id',
                'products.name as product_name',
                DB::raw('SUM(order_items.quantity) as units_sold'),
                DB::raw('SUM(order_items.quantity * order_items.price) as revenue'),
            ]);
    }

    public function branchScopeLabel(string $branchFilter, ?User $user = null): string
    {
        $user ??= auth()->user();

        if ($branchFilter === 'all') {
            return $user?->isBranchRestricted()
                ? 'All assigned branches'
                : 'All branches';
        }

        return Branch::query()->whereKey($branchFilter)->value('name') ?? 'Branch';
    }

    /**
     * @return array{
     *     branch_filter: string,
     *     branch_scope_label: string,
     *     inventory: array{unique_products: int, total_quantity: int},
     *     sales: array{units_sold: int, revenue: float},
     *     top_products: Collection,
     *     month_label: string
     * }
     */
    public function buildReport(string $branchFilter, ?User $user = null, ?Carbon $month = null): array
    {
        $user ??= auth()->user();
        $month ??= now();
        $branchIds = $this->resolveBranchIds($branchFilter, $user);

        return [
            'branch_filter' => $branchFilter,
            'branch_scope_label' => $this->branchScopeLabel($branchFilter, $user),
            'inventory' => $this->inventoryStats($branchIds),
            'sales' => $this->salesSummaryForMonth($branchIds, $month),
            'top_products' => $this->topSellingProductsForMonth($branchIds, 10, $month),
            'month_label' => $month->format('F Y'),
        ];
    }
}
