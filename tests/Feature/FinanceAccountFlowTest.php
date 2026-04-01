<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Services\BankTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceAccountFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_payment_increases_the_account_linked_to_its_payment_type(): void
    {
        $branch = Branch::query()->create([
            'name' => 'Kazanchis',
            'code' => 'kazanchis',
            'is_active' => true,
            'is_default' => false,
        ]);

        $cashAccount = BankAccount::query()->create([
            'name' => 'Cash Holding',
            'branch_id' => $branch->id,
            'currency' => 'ETB',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_default' => true,
        ]);

        $bankAccount = BankAccount::query()->create([
            'name' => 'Primary Bank',
            'branch_id' => $branch->id,
            'currency' => 'ETB',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_default' => false,
        ]);

        $bankTransfer = PaymentType::query()->create([
            'name' => 'Bank Transfer',
            'branch_id' => $branch->id,
            'bank_account_id' => $bankAccount->id,
            'is_active' => true,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Test Customer',
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 150,
            'status' => 'completed',
        ]);

        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'payment_type_id' => $bankTransfer->id,
            'amount' => 150,
            'payment_method' => 'Bank Transfer',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $bankAccount->id,
            'branch_id' => $branch->id,
            'reference_type' => Payment::class,
            'reference_id' => $payment->id,
            'type' => BankTransaction::TYPE_DEPOSIT,
            'amount' => '150.00',
        ]);

        $this->assertSame(0.0, (float) $cashAccount->fresh()->current_balance);
        $this->assertSame(150.0, (float) $bankAccount->fresh()->current_balance);
    }

    public function test_transfer_moves_money_between_accounts_and_deleting_one_side_reverts_both(): void
    {
        $branch = Branch::query()->create([
            'name' => '4 Kilo',
            'code' => '4kilo',
            'is_active' => true,
            'is_default' => false,
        ]);

        $cashAccount = BankAccount::query()->create([
            'name' => 'Cash Holding',
            'branch_id' => $branch->id,
            'currency' => 'ETB',
            'opening_balance' => 0,
            'current_balance' => 100,
            'is_default' => true,
        ]);

        $bankAccount = BankAccount::query()->create([
            'name' => 'Primary Bank',
            'branch_id' => $branch->id,
            'currency' => 'ETB',
            'opening_balance' => 0,
            'current_balance' => 25,
            'is_default' => false,
        ]);

        $transfer = app(BankTransactionService::class)->createTransfer(
            $cashAccount,
            $bankAccount,
            40,
            ['date' => now()->toDateString(), 'description' => 'Cash deposit'],
        );

        $linkedTransfer = $transfer->linkedTransaction()->first();

        $this->assertNotNull($linkedTransfer);
        $this->assertTrue($transfer->isTransferEntry());
        $this->assertTrue($linkedTransfer->isTransferEntry());
        $this->assertSame(60.0, (float) $cashAccount->fresh()->current_balance);
        $this->assertSame(65.0, (float) $bankAccount->fresh()->current_balance);

        $transfer->delete();

        $this->assertDatabaseMissing('bank_transactions', ['id' => $transfer->id]);
        $this->assertDatabaseMissing('bank_transactions', ['id' => $linkedTransfer->id]);
        $this->assertSame(100.0, (float) $cashAccount->fresh()->current_balance);
        $this->assertSame(25.0, (float) $bankAccount->fresh()->current_balance);
    }
}
