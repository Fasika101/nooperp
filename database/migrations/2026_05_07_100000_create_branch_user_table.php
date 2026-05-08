<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_user', function (Blueprint $table) {
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['branch_id', 'user_id']);
        });

        // Seed pivot from existing users.branch_id (non-null rows only)
        $rows = DB::table('users')
            ->whereNotNull('branch_id')
            ->pluck('branch_id', 'id');

        foreach ($rows as $userId => $branchId) {
            DB::table('branch_user')->insertOrIgnore([
                'user_id'   => $userId,
                'branch_id' => $branchId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user');
    }
};
