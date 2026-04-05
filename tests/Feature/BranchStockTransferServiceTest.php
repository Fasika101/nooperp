<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\BranchStockTransfer;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\BranchStockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BranchStockTransferServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_moves_quantity_and_blends_avg_cost(): void
    {
        $from = Branch::query()->create([
            'name' => 'From B',
            'code' => 'from',
            'is_active' => true,
            'is_default' => false,
        ]);

        $to = Branch::query()->create([
            'name' => 'To B',
            'code' => 'to',
            'is_active' => true,
            'is_default' => false,
        ]);

        $category = Category::query()->create(['name' => 'X']);
        $product = Product::query()->create([
            'name' => 'Movable',
            'category_id' => $category->id,
            'price' => 50,
            'stock' => 12,
            'cost_price' => 20,
        ]);

        BranchProductStock::query()->create([
            'branch_id' => $from->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'avg_cost' => 20,
        ]);

        BranchProductStock::query()->create([
            'branch_id' => $to->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'avg_cost' => 30,
        ]);

        $user = User::factory()->create();

        $record = app(BranchStockTransferService::class)->transfer([
            'product_id' => $product->id,
            'from_branch_id' => $from->id,
            'to_branch_id' => $to->id,
            'quantity' => 4,
            'note' => 'Rebalance',
            'user_id' => $user->id,
        ]);

        $this->assertInstanceOf(BranchStockTransfer::class, $record);
        $this->assertSame(4, $record->quantity);

        $this->assertSame(6, (int) BranchProductStock::query()->where('branch_id', $from->id)->where('product_id', $product->id)->value('quantity'));
        $this->assertSame(6, (int) BranchProductStock::query()->where('branch_id', $to->id)->where('product_id', $product->id)->value('quantity'));

        $product->refresh();
        $this->assertSame(12, $product->stock);
    }

    public function test_transfer_rejects_insufficient_source_stock(): void
    {
        $from = Branch::query()->create([
            'name' => 'Low',
            'code' => 'low',
            'is_active' => true,
            'is_default' => false,
        ]);

        $to = Branch::query()->create([
            'name' => 'High',
            'code' => 'high',
            'is_active' => true,
            'is_default' => false,
        ]);

        $category = Category::query()->create(['name' => 'Y']);
        $product = Product::query()->create([
            'name' => 'Rare',
            'category_id' => $category->id,
            'price' => 10,
            'stock' => 1,
            'cost_price' => 5,
        ]);

        BranchProductStock::query()->create([
            'branch_id' => $from->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'avg_cost' => 5,
        ]);

        $this->expectException(ValidationException::class);

        app(BranchStockTransferService::class)->transfer([
            'product_id' => $product->id,
            'from_branch_id' => $from->id,
            'to_branch_id' => $to->id,
            'quantity' => 3,
        ]);
    }
}
