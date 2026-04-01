<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\BranchProductStock;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Product;
use App\Models\StockPurchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockPurchaseService
{
    public function create(array $data): StockPurchase
    {
        $data['total_cost'] = round((float) ($data['quantity'] ?? 0) * (float) ($data['unit_cost'] ?? 0), 2);
        $data['branch_id'] = $data['branch_id'] ?? Branch::getDefaultBranch()?->id;

        return DB::transaction(function () use ($data) {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            $account = BankAccount::query()->lockForUpdate()->findOrFail($data['bank_account_id']);
            $branch = Branch::query()->findOrFail($data['branch_id']);
            $totalCost = (float) $data['total_cost'];

            if ((float) $account->current_balance < $totalCost) {
                throw ValidationException::withMessages([
                    'bank_account_id' => "Insufficient balance in {$account->name}.",
                ]);
            }

            if ($account->branch_id && (int) $account->branch_id !== (int) $branch->id) {
                throw ValidationException::withMessages([
                    'bank_account_id' => "The selected account belongs to {$account->branch?->name}, not {$branch->name}.",
                ]);
            }

            $purchase = StockPurchase::query()->create($data);

            $oldStock = (int) $product->stock;
            $newQty = (int) $purchase->quantity;
            $newCost = (float) $purchase->unit_cost;
            $oldCost = (float) ($product->cost_price ?? $product->original_price ?? 0);
            $totalStock = $oldStock + $newQty;

            $branchStock = BranchProductStock::query()->firstOrNew([
                'branch_id' => $branch->id,
                'product_id' => $product->id,
            ]);

            $existingBranchQty = (int) ($branchStock->exists ? $branchStock->quantity : 0);
            $existingBranchCost = (float) ($branchStock->avg_cost ?? $oldCost);
            $branchTotalQty = $existingBranchQty + $newQty;

            $branchStock->fill([
                'quantity' => $branchTotalQty,
                'avg_cost' => $branchTotalQty > 0
                    ? round((($existingBranchQty * $existingBranchCost) + ($newQty * $newCost)) / $branchTotalQty, 2)
                    : $newCost,
            ])->save();

            $product->update([
                'cost_price' => $totalStock > 0
                    ? round((($oldStock * $oldCost) + ($newQty * $newCost)) / $totalStock, 2)
                    : $newCost,
                'price' => (float) $purchase->sale_price,
            ]);

            $inventoryExpenseType = ExpenseType::query()->firstOrCreate(
                ['name' => 'Inventory Purchase'],
                ['is_active' => true],
            );

            $expense = Expense::query()->create([
                'date' => $purchase->date,
                'amount' => $purchase->total_cost,
                'expense_type_id' => $inventoryExpenseType->id,
                'bank_account_id' => $account->id,
                'branch_id' => $branch->id,
                'vendor' => $purchase->vendor,
                'description' => "Restock: {$product->name} ({$purchase->quantity} units) - {$branch->name}",
            ]);

            $purchase->update([
                'expense_id' => $expense->id,
            ]);

            return $purchase->fresh(['product', 'expense', 'bankAccount', 'branch']);
        });
    }
}
