<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Services\AccountsReceivableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsReceivableTest extends TestCase
{
    use RefreshDatabase;

    public function test_on_account_payment_type_does_not_create_bank_transaction(): void
    {
        $branch = Branch::query()->create([
            'name' => 'AR Test Branch A',
            'code' => 'ar-a-'.uniqid(),
            'is_active' => true,
            'is_default' => false,
        ]);

        $arType = PaymentType::query()->create([
            'name' => 'On Account',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_accounts_receivable' => true,
            'bank_account_id' => null,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Credit Customer',
            'phone' => '0911000000',
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 100,
            'amount_paid' => 0,
            'balance_due' => 100,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'status' => 'completed',
        ]);

        Payment::query()->create([
            'order_id' => $order->id,
            'branch_id' => $branch->id,
            'payment_type_id' => $arType->id,
            'amount' => 100,
            'payment_method' => 'On Account',
            'status' => 'completed',
        ]);

        $this->assertDatabaseMissing('bank_transactions', [
            'reference_type' => Payment::class,
        ]);
    }

    public function test_collection_payment_reduces_order_balance_and_deposits_bank(): void
    {
        $branch = Branch::query()->create([
            'name' => 'AR Test Branch B',
            'code' => 'ar-b-'.uniqid(),
            'is_active' => true,
            'is_default' => false,
        ]);

        $account = BankAccount::query()->create([
            'name' => 'Cash',
            'branch_id' => $branch->id,
            'currency' => 'ETB',
            'opening_balance' => 0,
            'current_balance' => 0,
            'is_default' => true,
        ]);

        $cashType = PaymentType::query()->create([
            'name' => 'Cash',
            'branch_id' => $branch->id,
            'is_active' => true,
            'is_accounts_receivable' => false,
            'bank_account_id' => $account->id,
        ]);

        $customer = Customer::query()->create([
            'name' => 'Payer',
            'phone' => '0922000000',
        ]);

        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'branch_id' => $branch->id,
            'total_amount' => 200,
            'amount_paid' => 0,
            'balance_due' => 200,
            'payment_status' => Order::PAYMENT_STATUS_UNPAID,
            'status' => 'completed',
        ]);

        app(AccountsReceivableService::class)->recordCollectionPayment($order, $cashType->id, 80.0);

        $order->refresh();

        $this->assertSame(80.0, (float) $order->amount_paid);
        $this->assertSame(120.0, (float) $order->balance_due);
        $this->assertSame(Order::PAYMENT_STATUS_PARTIAL, $order->payment_status);

        $this->assertDatabaseHas('bank_transactions', [
            'bank_account_id' => $account->id,
            'reference_type' => Payment::class,
            'amount' => '80.00',
        ]);
    }
}
