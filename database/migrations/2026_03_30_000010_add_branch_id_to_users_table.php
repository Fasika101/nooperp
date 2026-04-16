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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('email')->constrained()->nullOnDelete();
        });

        $defaultBranchId = DB::table('branches')->where('is_default', true)->value('id')
            ?? DB::table('branches')->orderBy('id')->value('id');

        if ($defaultBranchId) {
            DB::table('users')->update(['branch_id' => $defaultBranchId]);
        }
    }

    public function down(): void
    {
        $this->dropForeignKeyIfExists('users', 'branch_id');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('branch_id');
        });
    }
};
