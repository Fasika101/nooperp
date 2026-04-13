<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->decimal('credit_limit', 12, 2)->nullable()->after('tin')->comment('Null = no limit');
            $table->boolean('credit_blocked')->default(false)->after('credit_limit');
        });

        Schema::table('payment_types', function (Blueprint $table) {
            $table->boolean('is_accounts_receivable')->default(false)->after('bank_account_id');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('amount_paid', 12, 2)->default(0)->after('total_amount');
            $table->decimal('balance_due', 12, 2)->default(0)->after('amount_paid');
            $table->string('payment_status', 20)->default('paid')->after('balance_due');
            $table->date('due_date')->nullable()->after('payment_status');
        });

        $this->backfillOrdersFromPayments();
    }

    protected function backfillOrdersFromPayments(): void
    {
        $orders = DB::table('orders')->select('id', 'total_amount')->get();

        foreach ($orders as $order) {
            $paid = (float) DB::table('payments')
                ->where('order_id', $order->id)
                ->where('status', 'completed')
                ->sum('amount');

            $total = (float) $order->total_amount;
            $balance = round(max(0, $total - $paid), 2);
            $status = $balance <= 0.01
                ? 'paid'
                : ($paid > 0 ? 'partial' : 'unpaid');

            DB::table('orders')->where('id', $order->id)->update([
                'amount_paid' => round($paid, 2),
                'balance_due' => $balance,
                'payment_status' => $status,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['amount_paid', 'balance_due', 'payment_status', 'due_date']);
        });

        Schema::table('payment_types', function (Blueprint $table) {
            $table->dropColumn('is_accounts_receivable');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['credit_limit', 'credit_blocked']);
        });
    }
};
