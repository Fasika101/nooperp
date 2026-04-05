<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ProductOption;
use App\Models\StockPurchase;
use App\Services\ProductCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_product_with_initial_stock_creates_expense_and_deducts_selected_account(): void
    {
        $branch = Branch::query()->create([
            'name' => 'CMC',
            'code' => 'cmc',
            'is_active' => true,
            'is_default' => false,
        ]);

        $account = BankAccount::query()->create([
            'name' => 'Telebirr Account',
            'branch_id' => $branch->id,
            'currency' => 'ETB',
            'opening_balance' => 600,
            'current_balance' => 600,
            'is_default' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Accessories',
        ]);

        $brand = ProductOption::query()->create([
            'type' => ProductOption::TYPE_BRAND,
            'name' => 'Ray House',
        ]);

        $color = ProductOption::query()->create([
            'type' => ProductOption::TYPE_COLOR,
            'name' => 'Black',
        ]);

        $size = ProductOption::query()->create([
            'type' => ProductOption::TYPE_SIZE,
            'name' => 'Medium',
        ]);

        $product = app(ProductCreationService::class)->create([
            'name' => 'Chain Case',
            'category_id' => $category->id,
            'brand_option_id' => $brand->id,
            'color_option_id' => $color->id,
            'size_option_id' => $size->id,
            'original_price' => 90,
            'cost_price' => 40,
            'price' => 75,
            'stock' => 5,
            'initial_stock_branch_id' => $branch->id,
            'initial_stock_bank_account_id' => $account->id,
            'initial_stock_date' => now()->toDateString(),
            'initial_stock_vendor' => 'Starter Supplier',
        ]);

        $product->refresh();
        $account->refresh();

        $this->assertSame($brand->id, $product->brand_option_id);
        $this->assertSame($color->id, $product->color_option_id);
        $this->assertSame($size->id, $product->size_option_id);
        $this->assertSame(5, $product->stock);
        $this->assertSame(40.0, (float) $product->cost_price);
        $this->assertSame(75.0, (float) $product->price);
        $this->assertSame(400.0, (float) $account->current_balance);

        $purchase = StockPurchase::query()->firstOrFail();
        $expense = Expense::query()->findOrFail($purchase->expense_id);

        $this->assertSame($product->id, $purchase->product_id);
        $this->assertSame($branch->id, $purchase->branch_id);
        $this->assertSame($account->id, $purchase->bank_account_id);
        $this->assertSame(200.0, (float) $purchase->total_cost);
        $this->assertSame($branch->id, $expense->branch_id);
        $this->assertSame($account->id, $expense->bank_account_id);

        $this->assertDatabaseHas('branch_product_stocks', [
            'branch_id' => $branch->id,
            'product_id' => $product->id,
            'quantity' => 5,
        ]);

        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $account->id,
            'reference_type' => Expense::class,
            'reference_id' => $expense->id,
            'type' => BankTransaction::TYPE_WITHDRAWAL,
            'amount' => '200.00',
        ]);
    }

    public function test_new_product_with_initial_stock_is_blocked_if_selected_account_is_too_low(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Megenagna',
            'code' => 'megenagna',
            'is_active' => true,
            'is_default' => false,
        ]);

        $account = BankAccount::query()->create([
            'name' => 'Cash Holding',
            'branch_id' => $branch->id,
            'currency' => 'ETB',
            'opening_balance' => 80,
            'current_balance' => 80,
            'is_default' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Cases',
        ]);

        try {
            app(ProductCreationService::class)->create([
                'name' => 'Premium Case',
                'category_id' => $category->id,
                'original_price' => 130,
                'cost_price' => 50,
                'price' => 110,
                'stock' => 2,
                'initial_stock_branch_id' => $branch->id,
                'initial_stock_bank_account_id' => $account->id,
                'initial_stock_date' => now()->toDateString(),
                'initial_stock_vendor' => 'Starter Supplier',
            ]);

            $this->fail('Expected insufficient balance validation to be thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                "Insufficient balance in {$account->name}.",
                $exception->errors()['bank_account_id'][0] ?? null,
            );
        }

        $account->refresh();

        $this->assertSame(80.0, (float) $account->current_balance);
        $this->assertFalse(Product::query()->where('name', 'Premium Case')->exists());
        $this->assertDatabaseCount('stock_purchases', 0);
        $this->assertDatabaseCount('expenses', 0);
        $this->assertDatabaseCount('bank_transactions', 0);
        $this->assertDatabaseCount('branch_product_stocks', 0);
    }

    public function test_new_product_initial_stock_can_split_across_branches_with_single_expense(): void
    {
        $branchA = Branch::query()->create([
            'name' => 'Alpha',
            'code' => 'alpha',
            'is_active' => true,
            'is_default' => true,
        ]);

        $branchB = Branch::query()->create([
            'name' => 'Beta',
            'code' => 'beta',
            'is_active' => true,
            'is_default' => false,
        ]);

        $account = BankAccount::query()->create([
            'name' => 'HQ Account',
            'branch_id' => $branchA->id,
            'currency' => 'ETB',
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'is_default' => true,
        ]);

        $category = Category::query()->create([
            'name' => 'Frames',
        ]);

        $product = app(ProductCreationService::class)->create([
            'name' => 'Split Frame',
            'category_id' => $category->id,
            'original_price' => 120,
            'cost_price' => 40,
            'price' => 99,
            'stock' => 5,
            'initial_stock_allocations' => [
                ['branch_id' => $branchA->id, 'quantity' => 2],
                ['branch_id' => $branchB->id, 'quantity' => 3],
            ],
            'initial_stock_bank_account_id' => $account->id,
            'initial_stock_date' => now()->toDateString(),
            'initial_stock_vendor' => 'Vendor',
        ]);

        $product->refresh();
        $account->refresh();

        $this->assertSame(5, $product->stock);
        $this->assertSame(800.0, (float) $account->current_balance);

        $this->assertSame(2, StockPurchase::query()->count());
        $this->assertSame(1, Expense::query()->count());

        $linkedExpenseIds = StockPurchase::query()->pluck('expense_id')->filter()->values()->all();
        $this->assertCount(1, $linkedExpenseIds);
        $this->assertSame(200.0, (float) Expense::query()->firstOrFail()->amount);

        $this->assertDatabaseHas('branch_product_stocks', [
            'branch_id' => $branchA->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
        $this->assertDatabaseHas('branch_product_stocks', [
            'branch_id' => $branchB->id,
            'product_id' => $product->id,
            'quantity' => 3,
        ]);
    }
}
