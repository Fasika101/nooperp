<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockPurchase;
use App\Services\StockPurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StockPurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_restock_updates_stock_sale_price_expense_and_account_balance(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Bole',
            'code' => 'bole',
            'is_active' => true,
            'is_default' => false,
        ]);

        $account = BankAccount::query()->create([
            'name' => 'Main Bank',
            'branch_id' => $branch->id,
            'currency' => 'ETB',
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'is_default' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Frames',
        ]);

        $product = Product::query()->create([
            'name' => 'Classic Frame',
            'category_id' => $category->id,
            'price' => 100,
            'cost_price' => 80,
            'stock' => 2,
        ]);

        $variant = ProductVariant::findOrCreateForProduct($product->id, null, null);

        BranchProductStock::query()->create([
            'branch_id' => $branch->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
            'avg_cost' => 80,
        ]);

        $purchase = app(StockPurchaseService::class)->create([
            'product_id' => $product->id,
            'branch_id' => $branch->id,
            'quantity' => 3,
            'unit_cost' => 60,
            'sale_price' => 120,
            'vendor' => 'Optics Supplier',
            'date' => now()->toDateString(),
            'bank_account_id' => $account->id,
        ]);

        $product->refresh();
        $account->refresh();

        $this->assertInstanceOf(StockPurchase::class, $purchase);
        $this->assertSame(3, $purchase->quantity);
        $this->assertSame(60.0, (float) $purchase->unit_cost);
        $this->assertSame(120.0, (float) $purchase->sale_price);
        $this->assertSame(180.0, (float) $purchase->total_cost);
        $this->assertSame($branch->id, $purchase->branch_id);
        $this->assertSame($account->id, $purchase->bank_account_id);
        $this->assertNotNull($purchase->expense_id);

        $this->assertSame(5, $product->stock);
        $this->assertSame(68.0, (float) $product->cost_price);
        $this->assertSame(120.0, (float) $product->price);

        $expense = Expense::query()->findOrFail($purchase->expense_id);
        $this->assertSame($branch->id, $expense->branch_id);
        $this->assertSame($account->id, $expense->bank_account_id);
        $this->assertSame(180.0, (float) $expense->amount);

        $this->assertDatabaseHas('branch_product_stocks', [
            'branch_id' => $branch->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5,
        ]);

        $this->assertSame(820.0, (float) $account->current_balance);

        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $account->id,
            'reference_type' => Expense::class,
            'reference_id' => $expense->id,
            'type' => BankTransaction::TYPE_WITHDRAWAL,
            'amount' => '180.00',
        ]);
    }

    public function test_restock_is_blocked_when_selected_account_has_insufficient_balance(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Piassa',
            'code' => 'piassa',
            'is_active' => true,
            'is_default' => false,
        ]);

        $account = BankAccount::query()->create([
            'name' => 'Cash Holding',
            'branch_id' => $branch->id,
            'currency' => 'ETB',
            'opening_balance' => 50,
            'current_balance' => 50,
            'is_default' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Lenses',
        ]);

        $product = Product::query()->create([
            'name' => 'Blue Lens',
            'category_id' => $category->id,
            'price' => 80,
            'cost_price' => 50,
            'stock' => 4,
        ]);

        $variant = ProductVariant::findOrCreateForProduct($product->id, null, null);

        BranchProductStock::query()->create([
            'branch_id' => $branch->id,
            'product_variant_id' => $variant->id,
            'quantity' => 4,
            'avg_cost' => 50,
        ]);

        try {
            app(StockPurchaseService::class)->create([
                'product_id' => $product->id,
                'branch_id' => $branch->id,
                'quantity' => 2,
                'unit_cost' => 40,
                'sale_price' => 90,
                'vendor' => 'Lens Supplier',
                'date' => now()->toDateString(),
                'bank_account_id' => $account->id,
            ]);

            $this->fail('Expected insufficient balance validation to be thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                "Insufficient balance in {$account->name}.",
                $exception->errors()['bank_account_id'][0] ?? null,
            );
        }

        $product->refresh();
        $account->refresh();

        $this->assertSame(4, $product->stock);
        $this->assertSame(80.0, (float) $product->price);
        $this->assertSame(50.0, (float) $account->current_balance);
        $this->assertDatabaseCount('stock_purchases', 0);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('bank_transactions', 0);
        $this->assertDatabaseHas('branch_product_stocks', [
            'branch_id' => $branch->id,
            'product_variant_id' => $variant->id,
            'quantity' => 4,
        ]);
    }
}
