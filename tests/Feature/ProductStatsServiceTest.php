<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProductStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_stats_count_unique_products_and_total_quantity_for_branch(): void
    {
        [$branchA, $branchB] = $this->makeTwoBranches();
        $productA = $this->makeInventoryProduct('Frame A');
        $productB = $this->makeInventoryProduct('Frame B');

        $this->stockProductAtBranch($productA, $branchA, 5);
        $this->stockProductAtBranch($productB, $branchA, 5);
        $this->stockProductAtBranch($productA, $branchB, 3);

        $stats = app(ProductStatsService::class);

        $branchAStats = $stats->inventoryStats([$branchA->id]);
        $this->assertSame(2, $branchAStats['unique_products']);
        $this->assertSame(10, $branchAStats['total_quantity']);

        $allStats = $stats->inventoryStats([$branchA->id, $branchB->id]);
        $this->assertSame(2, $allStats['unique_products']);
        $this->assertSame(13, $allStats['total_quantity']);
    }

    public function test_top_selling_products_this_month_respects_branch_and_excludes_services(): void
    {
        [$branchA, $branchB] = $this->makeTwoBranches();
        $frame = $this->makeInventoryProduct('Best Frame');
        $other = $this->makeInventoryProduct('Other Frame');
        $this->makeInventoryProduct('Lens Service', isService: true);

        $customer = Customer::query()->create([
            'name' => 'Buyer',
            'email' => 'buyer@example.com',
        ]);

        $orderA = Order::query()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branchA->id,
            'total_amount' => 300,
            'amount_paid' => 300,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'status' => 'completed',
            'created_at' => now(),
        ]);

        OrderItem::query()->create([
            'order_id' => $orderA->id,
            'product_id' => $frame->id,
            'quantity' => 3,
            'price' => 100,
        ]);

        OrderItem::query()->create([
            'order_id' => $orderA->id,
            'product_id' => $other->id,
            'quantity' => 1,
            'price' => 50,
        ]);

        $orderB = Order::query()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branchB->id,
            'total_amount' => 100,
            'amount_paid' => 100,
            'payment_status' => Order::PAYMENT_STATUS_PAID,
            'status' => 'completed',
            'created_at' => now(),
        ]);

        OrderItem::query()->create([
            'order_id' => $orderB->id,
            'product_id' => $frame->id,
            'quantity' => 1,
            'price' => 100,
        ]);

        $stats = app(ProductStatsService::class);
        $topA = $stats->topSellingProductsForMonth([$branchA->id]);

        $this->assertCount(2, $topA);
        $this->assertSame($frame->id, (int) $topA->first()->product_id);
        $this->assertSame(3, (int) $topA->first()->units_sold);
        $this->assertSame(300.0, (float) $topA->first()->revenue);

        $topB = $stats->topSellingProductsForMonth([$branchB->id]);
        $this->assertCount(1, $topB);
        $this->assertSame(1, (int) $topB->first()->units_sold);
    }

    public function test_branch_restricted_user_only_sees_assigned_branches_in_filter(): void
    {
        [$branchA, $branchB] = $this->makeTwoBranches();
        $user = User::factory()->create();
        Role::findOrCreate('staff');
        $user->assignRole('staff');
        $user->branches()->attach($branchA->id);

        $stats = app(ProductStatsService::class);
        $options = $stats->branchFilterOptions($user);

        $this->assertArrayHasKey((string) $branchA->id, $options);
        $this->assertArrayNotHasKey((string) $branchB->id, $options);
        $this->assertArrayNotHasKey('all', $options);
        $this->assertSame([(int) $branchA->id], $stats->resolveBranchIds('all', $user));
    }

    public function test_admin_can_filter_all_branches_or_single_branch(): void
    {
        [$branchA, $branchB] = $this->makeTwoBranches();
        $admin = User::factory()->create();
        Role::findOrCreate('super_admin');
        $admin->assignRole('super_admin');

        $stats = app(ProductStatsService::class);
        $options = $stats->branchFilterOptions($admin);

        $this->assertArrayHasKey('all', $options);
        $this->assertSame(
            Branch::query()->where('is_active', true)->pluck('id')->map(fn ($id): int => (int) $id)->sort()->values()->all(),
            collect($stats->resolveBranchIds('all', $admin))->sort()->values()->all(),
        );
        $this->assertSame([(int) $branchB->id], $stats->resolveBranchIds((string) $branchB->id, $admin));
    }

    /**
     * @return array{0: Branch, 1: Branch}
     */
    protected function makeTwoBranches(): array
    {
        $branchA = Branch::query()->create([
            'name' => 'Branch A',
            'code' => 'branch-a',
            'is_active' => true,
            'is_default' => true,
        ]);

        $branchB = Branch::query()->create([
            'name' => 'Branch B',
            'code' => 'branch-b',
            'is_active' => true,
            'is_default' => false,
        ]);

        return [$branchA, $branchB];
    }

    protected function makeInventoryProduct(string $name, bool $isService = false): Product
    {
        $category = Category::query()->firstOrCreate(['name' => 'Frames']);

        return Product::query()->create([
            'name' => $name,
            'category_id' => $category->id,
            'price' => 100,
            'cost_price' => 50,
            'stock' => 0,
            'is_service' => $isService,
        ]);
    }

    protected function stockProductAtBranch(Product $product, Branch $branch, int $quantity): void
    {
        $variant = ProductVariant::findOrCreateForProduct($product->id, null, null);

        BranchProductStock::query()->create([
            'branch_id' => $branch->id,
            'product_variant_id' => $variant->id,
            'quantity' => $quantity,
        ]);
    }
}
