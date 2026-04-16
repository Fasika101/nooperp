<?php

use App\Support\Migration\DropsForeignKeysSafely;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use DropsForeignKeysSafely;

    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('bank_account_id')
                ->nullable()
                ->after('expense_type_id')
                ->constrained('bank_accounts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('expenses', 'bank_account_id');
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('bank_account_id');
        });
    }
};
