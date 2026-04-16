<?php

use App\Support\Migration\DropsForeignKeysSafely;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use DropsForeignKeysSafely;

    public function up(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('name')->constrained()->nullOnDelete();
        });

        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('bank_account_id')->constrained()->nullOnDelete();
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('bank_account_id')->constrained()->nullOnDelete();
        });

        Schema::table('stock_purchases', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('order_id')->constrained()->nullOnDelete();
        });

        Schema::table('payment_types', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('name')->constrained()->nullOnDelete();
        });

        $defaultBranchId = DB::table('branches')->where('is_default', true)->value('id')
            ?? DB::table('branches')->orderBy('id')->value('id');

        if (! $defaultBranchId) {
            return;
        }

        DB::table('bank_accounts')->update(['branch_id' => $defaultBranchId]);
        DB::table('bank_transactions')->update(['branch_id' => $defaultBranchId]);
        DB::table('expenses')->update(['branch_id' => $defaultBranchId]);
        DB::table('stock_purchases')->update(['branch_id' => $defaultBranchId]);
        DB::table('orders')->update(['branch_id' => $defaultBranchId]);
        DB::table('payments')->update(['branch_id' => $defaultBranchId]);
        DB::table('payment_types')->update(['branch_id' => $defaultBranchId]);

        $bankAccountBranches = DB::table('bank_accounts')
            ->select('id', 'branch_id')
            ->get()
            ->pluck('branch_id', 'id');

        DB::table('bank_transactions')
            ->select('id', 'bank_account_id')
            ->get()
            ->each(function ($transaction) use ($bankAccountBranches, $defaultBranchId): void {
                DB::table('bank_transactions')
                    ->where('id', $transaction->id)
                    ->update([
                        'branch_id' => $bankAccountBranches[$transaction->bank_account_id] ?? $defaultBranchId,
                    ]);
            });

        DB::table('expenses')
            ->select('id', 'bank_account_id')
            ->get()
            ->each(function ($expense) use ($bankAccountBranches, $defaultBranchId): void {
                DB::table('expenses')
                    ->where('id', $expense->id)
                    ->update([
                        'branch_id' => $bankAccountBranches[$expense->bank_account_id] ?? $defaultBranchId,
                    ]);
            });

        $orderBranches = DB::table('orders')
            ->select('id', 'branch_id')
            ->get()
            ->pluck('branch_id', 'id');

        DB::table('payments')
            ->select('id', 'order_id')
            ->get()
            ->each(function ($payment) use ($orderBranches, $defaultBranchId): void {
                DB::table('payments')
                    ->where('id', $payment->id)
                    ->update([
                        'branch_id' => $orderBranches[$payment->order_id] ?? $defaultBranchId,
                    ]);
            });
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('payment_types', 'branch_id');
        Schema::table('payment_types', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });

        $this->dropForeignKeyIfExists('payments', 'branch_id');
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });

        $this->dropForeignKeyIfExists('orders', 'branch_id');
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });

        $this->dropForeignKeyIfExists('stock_purchases', 'branch_id');
        Schema::table('stock_purchases', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });

        $this->dropForeignKeyIfExists('expenses', 'branch_id');
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });

        $this->dropForeignKeyIfExists('bank_transactions', 'branch_id');
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });

        $this->dropForeignKeyIfExists('bank_accounts', 'branch_id');
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });
    }
};
