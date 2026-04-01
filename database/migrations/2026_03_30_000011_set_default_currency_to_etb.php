<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('settings')
            ->where('key', 'default_currency')
            ->value('value');

        if ($existing === null) {
            DB::table('settings')->insert([
                'key' => 'default_currency',
                'value' => 'ETB',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        if ($existing === 'USD') {
            DB::table('settings')
                ->where('key', 'default_currency')
                ->update([
                    'value' => 'ETB',
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $existing = DB::table('settings')
            ->where('key', 'default_currency')
            ->value('value');

        if ($existing === 'ETB') {
            DB::table('settings')
                ->where('key', 'default_currency')
                ->update([
                    'value' => 'USD',
                    'updated_at' => now(),
                ]);
        }
    }
};
