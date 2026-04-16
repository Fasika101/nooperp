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
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->foreignId('linked_transaction_id')
                ->nullable()
                ->after('bank_account_id')
                ->constrained('bank_transactions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('bank_transactions', 'linked_transaction_id');
        Schema::table('bank_transactions', function (Blueprint $table) {
            $table->dropColumn('linked_transaction_id');
        });
    }
};
