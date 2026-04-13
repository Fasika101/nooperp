<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_account_branch', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['bank_account_id', 'branch_id']);
        });

        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->boolean('is_global')->default(false)->after('branch_id');
        });

        $now = now();

        DB::table('bank_accounts')
            ->select('id', 'branch_id')
            ->whereNotNull('branch_id')
            ->orderBy('id')
            ->get()
            ->each(function ($row) use ($now): void {
                DB::table('bank_account_branch')->insert([
                    'bank_account_id' => $row->id,
                    'branch_id' => $row->branch_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('bank_accounts', function (Blueprint $table) {
            $table->dropColumn('is_global');
        });

        Schema::dropIfExists('bank_account_branch');
    }
};
