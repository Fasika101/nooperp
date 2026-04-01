<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_product_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(0);
            $table->decimal('avg_cost', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'product_id']);
        });

        $defaultBranchId = DB::table('branches')->where('is_default', true)->value('id')
            ?? DB::table('branches')->orderBy('id')->value('id');

        if (! $defaultBranchId) {
            return;
        }

        $products = DB::table('products')->select('id', 'stock', 'cost_price', 'created_at', 'updated_at')->get();

        foreach ($products as $product) {
            DB::table('branch_product_stocks')->insert([
                'branch_id' => $defaultBranchId,
                'product_id' => $product->id,
                'quantity' => (int) ($product->stock ?? 0),
                'avg_cost' => $product->cost_price,
                'created_at' => $product->created_at ?? now(),
                'updated_at' => $product->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_product_stocks');
    }
};
