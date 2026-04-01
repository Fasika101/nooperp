<?php

namespace Tests\Feature;

use App\Filament\Pages\PosPage;
use App\Filament\Resources\BankAccountResource;
use App\Filament\Resources\BankTransactionResource;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\PaymentTypeResource;
use App\Filament\Resources\StockPurchaseResource;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\StockPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BranchAssignedUserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_branch_staff_pos_defaults_to_their_assigned_branch_and_locks_branch_choices(): void
    {
        $assignedBranch = Branch::query()->create([
            'name' => 'Bole',
            'code' => 'bole',
            'is_active' => true,
            'is_default' => false,
        ]);

        Branch::query()->create([
            'name' => 'Piassa',
            'code' => 'piassa',
            'is_active' => true,
            'is_default' => true,
        ]);

        $user = User::factory()->create([
            'branch_id' => $assignedBranch->id,
        ]);

        $this->actingAs($user);

        $page = app(PosPage::class);
        $page->mount();

        $this->assertSame($assignedBranch->id, $page->branchId);
        $this->assertTrue($page->isBranchLocked());
        $this->assertSame([$assignedBranch->id], $page->getBranches()->pluck('id')->all());
    }

    public function test_super_admin_pos_keeps_branch_selection_available(): void
    {
        $defaultBranch = Branch::query()->create([
            'name' => 'CMC',
            'code' => 'cmc',
            'is_active' => true,
            'is_default' => true,
        ]);

        $otherBranch = Branch::query()->create([
            'name' => 'Megenagna',
            'code' => 'megenagna',
            'is_active' => true,
            'is_default' => false,
        ]);

        Role::query()->create([
            'name' => 'super_admin',
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create([
            'branch_id' => $otherBranch->id,
        ]);
        $user->assignRole('super_admin');
        $user = $user->fresh();

        $this->assertTrue($user->hasRole('super_admin'));

        $this->actingAs($user);

        $page = app(PosPage::class);
        $page->mount();

        $this->assertFalse($page->isBranchLocked());
        $branchIds = $page->getBranches()->pluck('id')->all();

        $this->assertContains($defaultBranch->id, $branchIds);
        $this->assertContains($otherBranch->id, $branchIds);
    }

    public function test_branch_staff_resource_queries_only_return_records_for_their_branch(): void
    {
        [$branchA, $branchB] = $this->createBranches();

        $user = User::factory()->create([
            'branch_id' => $branchA->id,
        ]);

        $this->actingAs($user);

        $accountA = BankAccount::query()->create([
            'name' => 'Bole Main',
            'branch_id' => $branchA->id,
            'currency' => 'ETB',
            'opening_balance' => 1000,
            'current_balance' => 1000,
            'is_default' => true,
        ]);

        $accountB = BankAccount::query()->create([
            'name' => 'Piassa Main',
            'branch_id' => $branchB->id,
            'currency' => 'ETB',
            'opening_balance' => 800,
            'current_balance' => 800,
            'is_default' => true,
        ]);

        $paymentTypeA = PaymentType::query()->create([
            'name' => 'Cash A',
            'branch_id' => $branchA->id,
            'bank_account_id' => $accountA->id,
            'is_active' => true,
        ]);

        $paymentTypeB = PaymentType::query()->create([
            'name' => 'Cash B',
            'branch_id' => $branchB->id,
            'bank_account_id' => $accountB->id,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Global Customer',
        ]);

        $orderA = Order::query()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branchA->id,
            'total_amount' => 120,
            'status' => 'completed',
        ]);

        $orderB = Order::query()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branchB->id,
            'total_amount' => 95,
            'status' => 'completed',
        ]);

        $paymentA = Payment::query()->create([
            'order_id' => $orderA->id,
            'branch_id' => $branchA->id,
            'payment_type_id' => $paymentTypeA->id,
            'amount' => 120,
            'payment_method' => $paymentTypeA->name,
            'status' => 'completed',
        ]);

        $paymentB = Payment::query()->create([
            'order_id' => $orderB->id,
            'branch_id' => $branchB->id,
            'payment_type_id' => $paymentTypeB->id,
            'amount' => 95,
            'payment_method' => $paymentTypeB->name,
            'status' => 'completed',
        ]);

        $expenseType = ExpenseType::query()->create([
            'name' => 'Supplies',
            'is_active' => true,
        ]);

        $expenseA = Expense::query()->create([
            'branch_id' => $branchA->id,
            'bank_account_id' => $accountA->id,
            'expense_type_id' => $expenseType->id,
            'date' => now()->toDateString(),
            'amount' => 50,
            'vendor' => 'Vendor A',
            'description' => 'Expense A',
        ]);

        $expenseB = Expense::query()->create([
            'branch_id' => $branchB->id,
            'bank_account_id' => $accountB->id,
            'expense_type_id' => $expenseType->id,
            'date' => now()->toDateString(),
            'amount' => 30,
            'vendor' => 'Vendor B',
            'description' => 'Expense B',
        ]);

        $category = Category::query()->create([
            'name' => 'Frames',
        ]);

        $product = Product::query()->create([
            'name' => 'Shared Product',
            'category_id' => $category->id,
            'price' => 150,
            'cost_price' => 100,
            'stock' => 0,
        ]);

        $stockPurchaseA = StockPurchase::query()->create([
            'product_id' => $product->id,
            'branch_id' => $branchA->id,
            'bank_account_id' => $accountA->id,
            'expense_id' => $expenseA->id,
            'quantity' => 2,
            'unit_cost' => 40,
            'sale_price' => 150,
            'total_cost' => 80,
            'date' => now()->toDateString(),
            'vendor' => 'Supplier A',
        ]);

        $stockPurchaseB = StockPurchase::query()->create([
            'product_id' => $product->id,
            'branch_id' => $branchB->id,
            'bank_account_id' => $accountB->id,
            'expense_id' => $expenseB->id,
            'quantity' => 1,
            'unit_cost' => 35,
            'sale_price' => 140,
            'total_cost' => 35,
            'date' => now()->toDateString(),
            'vendor' => 'Supplier B',
        ]);

        $transactionA = BankTransaction::query()->create([
            'bank_account_id' => $accountA->id,
            'branch_id' => $branchA->id,
            'date' => now()->toDateString(),
            'type' => BankTransaction::TYPE_DEPOSIT,
            'amount' => 20,
            'description' => 'A',
        ]);

        $transactionB = BankTransaction::query()->create([
            'bank_account_id' => $accountB->id,
            'branch_id' => $branchB->id,
            'date' => now()->toDateString(),
            'type' => BankTransaction::TYPE_DEPOSIT,
            'amount' => 25,
            'description' => 'B',
        ]);

        $this->assertSame([$accountA->id], BankAccountResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$paymentTypeA->id], PaymentTypeResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$orderA->id], OrderResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$paymentA->id], PaymentResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$expenseA->id], ExpenseResource::getEloquentQuery()->pluck('id')->all());
        $this->assertSame([$stockPurchaseA->id], StockPurchaseResource::getEloquentQuery()->pluck('id')->all());
        $branchTransactionIds = BankTransactionResource::getEloquentQuery()->pluck('id')->all();

        $this->assertNotContains($accountB->id, BankAccountResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains($paymentTypeB->id, PaymentTypeResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains($orderB->id, OrderResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains($paymentB->id, PaymentResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains($expenseB->id, ExpenseResource::getEloquentQuery()->pluck('id')->all());
        $this->assertNotContains($stockPurchaseB->id, StockPurchaseResource::getEloquentQuery()->pluck('id')->all());
        $this->assertContains($transactionA->id, $branchTransactionIds);
        $this->assertNotContains($transactionB->id, $branchTransactionIds);
    }

    /**
     * @return array{0: Branch, 1: Branch}
     */
    protected function createBranches(): array
    {
        $branchA = Branch::query()->create([
            'name' => 'Bole',
            'code' => 'bole',
            'is_active' => true,
            'is_default' => true,
        ]);

        $branchB = Branch::query()->create([
            'name' => 'Piassa',
            'code' => 'piassa',
            'is_active' => true,
            'is_default' => false,
        ]);

        return [$branchA, $branchB];
    }
}
