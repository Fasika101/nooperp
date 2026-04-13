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
    /**
     * Split one purchase across branches (single bank charge, one expense). One stock_purchases row per branch; only the first is linked to the expense.
     *
     * @param  array{product_id: int, unit_cost: float, sale_price: float, bank_account_id: int, date: string, vendor?: ?string, lines: list<array{branch_id: int, quantity: int}>}  $data
     * @return list<StockPurchase>
     */
    public function createDistributed(array $data): array
    {
        $lines = self::mergeAllocationLines($data['lines'] ?? []);
        if ($lines === []) {
            throw ValidationException::withMessages([
                'lines' => ['Add at least one branch with a quantity greater than zero.'],
            ]);
        }

        $totalQty = array_sum(array_column($lines, 'quantity'));
        $unitCost = (float) ($data['unit_cost'] ?? 0);
        $totalCost = round($totalQty * $unitCost, 2);
        $salePrice = (float) ($data['sale_price'] ?? 0);

        return DB::transaction(function () use ($data, $lines, $totalQty, $unitCost, $totalCost, $salePrice): array {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);
            $account = BankAccount::query()->lockForUpdate()->findOrFail($data['bank_account_id']);

            if ((float) $account->current_balance < $totalCost) {
                throw ValidationException::withMessages([
                    'bank_account_id' => "Insufficient balance in {$account->name}.",
                ]);
            }

            $branchIdsForAccount = array_column($lines, 'branch_id');
            if (! $account->isUsableAtAllBranches($branchIdsForAccount)) {
                throw ValidationException::withMessages([
                    'bank_account_id' => 'The selected account is not available for every branch in this restock.',
                ]);
            }

            $oldStock = (int) $product->stock;
            $oldCost = (float) ($product->cost_price ?? 0);

            $purchases = [];
            foreach ($lines as $line) {
                $branch = Branch::query()->findOrFail($line['branch_id']);
                $qty = (int) $line['quantity'];
                $lineTotal = round($qty * $unitCost, 2);

                $purchase = StockPurchase::query()->create([
                    'product_id' => $product->id,
                    'branch_id' => $branch->id,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'sale_price' => $salePrice,
                    'total_cost' => $lineTotal,
                    'vendor' => $data['vendor'] ?? null,
                    'date' => $data['date'] ?? now()->toDateString(),
                    'bank_account_id' => $account->id,
                    'expense_id' => null,
                ]);

                self::incrementBranchStockAfterPurchase($product, $branch->id, $qty, $unitCost, $oldCost);

                $purchases[] = $purchase;
            }

            $totalStock = $oldStock + $totalQty;
            $product->update([
                'cost_price' => $totalStock > 0
                    ? round((($oldStock * $oldCost) + ($totalQty * $unitCost)) / $totalStock, 2)
                    : $unitCost,
                'price' => $salePrice,
            ]);

            $inventoryExpenseType = ExpenseType::query()->firstOrCreate(
                ['name' => 'Inventory Purchase'],
                ['is_active' => true],
            );

            $branchSummaries = [];
            foreach ($lines as $line) {
                $branch = Branch::query()->find($line['branch_id']);
                $branchSummaries[] = ($branch?->name ?? '?').': '.(int) $line['quantity'];
            }

            $expense = Expense::query()->create([
                'date' => $data['date'] ?? now()->toDateString(),
                'amount' => $totalCost,
                'expense_type_id' => $inventoryExpenseType->id,
                'bank_account_id' => $account->id,
                'branch_id' => (int) $lines[0]['branch_id'],
                'vendor' => $data['vendor'] ?? null,
                'description' => 'Restock: '.$product->name.' ('.$totalQty.' units): '.implode('; ', $branchSummaries),
            ]);

            $purchases[0]->update([
                'expense_id' => $expense->id,
            ]);

            return $purchases;
        });
    }

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

            if (! $account->isUsableAtBranch((int) $branch->id)) {
                throw ValidationException::withMessages([
                    'bank_account_id' => "The selected account is not available for {$branch->name}.",
                ]);
            }

            $purchase = StockPurchase::query()->create($data);

            $oldStock = (int) $product->stock;
            $newQty = (int) $purchase->quantity;
            $newCost = (float) $purchase->unit_cost;
            $oldCost = (float) ($product->cost_price ?? 0);
            $totalStock = $oldStock + $newQty;

            self::incrementBranchStockAfterPurchase($product, $branch->id, $newQty, $newCost, $oldCost);

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

    /**
     * @param  list<array{branch_id?: mixed, quantity?: mixed}>  $lines
     * @return list<array{branch_id: int, quantity: int}>
     */
    public static function mergeAllocationLines(array $lines): array
    {
        $merged = [];
        foreach ($lines as $line) {
            $bid = (int) ($line['branch_id'] ?? 0);
            $qty = (int) ($line['quantity'] ?? 0);
            if ($bid <= 0 || $qty <= 0) {
                continue;
            }
            $merged[$bid] = ($merged[$bid] ?? 0) + $qty;
        }

        return collect($merged)->map(fn (int $qty, int $bid) => ['branch_id' => $bid, 'quantity' => $qty])->values()->all();
    }

    protected static function incrementBranchStockAfterPurchase(Product $product, int $branchId, int $newQty, float $newCost, float $fallbackCost): void
    {
        $branchStock = BranchProductStock::query()->firstOrNew([
            'branch_id' => $branchId,
            'product_id' => $product->id,
        ]);

        $existingBranchQty = (int) ($branchStock->exists ? $branchStock->quantity : 0);
        $existingBranchCost = (float) ($branchStock->avg_cost ?? $fallbackCost);
        $branchTotalQty = $existingBranchQty + $newQty;

        $branchStock->fill([
            'quantity' => $branchTotalQty,
            'avg_cost' => $branchTotalQty > 0
                ? round((($existingBranchQty * $existingBranchCost) + ($newQty * $newCost)) / $branchTotalQty, 2)
                : $newCost,
        ])->save();
    }
}
