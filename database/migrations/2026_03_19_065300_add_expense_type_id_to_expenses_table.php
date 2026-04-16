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
            $table->foreignId('expense_type_id')->nullable()->after('amount')->constrained()->nullOnDelete();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('expenses', 'expense_type_id');
        Schema::table('expenses', function (Blueprint $table) {
            $table->string('category')->nullable()->after('amount');
        });
    }
};
