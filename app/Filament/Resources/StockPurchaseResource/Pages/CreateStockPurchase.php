<?php

namespace App\Filament\Resources\StockPurchaseResource\Pages;

use App\Filament\Resources\StockPurchaseResource;
use App\Models\Branch;
use App\Models\Product;
use App\Services\StockPurchaseService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateStockPurchase extends CreateRecord
{
    protected static string $resource = StockPurchaseResource::class;

    public function mount(): void
    {
        parent::mount();
        $productId = request()->query('product_id');
        $fill = [
            'restock_allocations' => [
                [
                    'branch_id' => auth()->user()?->branch_id ?: Branch::getDefaultBranch()?->id,
                    'quantity' => 1,
                ],
            ],
        ];
        if (filled($productId)) {
            $fill['product_id'] = $productId;
            $product = Product::query()->find($productId);
            if ($product) {
                $fill['sale_price'] = (float) $product->price;
            }
        }
        $this->form->fill($fill);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $raw = (array) ($data['restock_allocations'] ?? []);
        $product = Product::query()->find((int) ($data['product_id'] ?? 0));

        // Auto-fill color/size only if the product has exactly one option AND the user left it blank.
        if ($product && $product->availableColorOptions()->count() === 1) {
            $cid = (int) $product->availableColorOptions()->first()->id;
            foreach ($raw as &$row) {
                if (empty($row['color_option_id'])) {
                    $row['color_option_id'] = $cid;
                }
            }
            unset($row);
        }
        if ($product && $product->availableSizeOptions()->count() === 1) {
            $sid = (int) $product->availableSizeOptions()->first()->id;
            foreach ($raw as &$row) {
                if (empty($row['size_option_id'])) {
                    $row['size_option_id'] = $sid;
                }
            }
            unset($row);
        }

        $lines = StockPurchaseService::mergeAllocationLines($raw);

        if ($lines === []) {
            throw ValidationException::withMessages([
                'restock_allocations' => ['Add at least one branch with a quantity greater than zero.'],
            ]);
        }

        if (auth()->user()?->isBranchRestricted()) {
            $allowedBranchIds = auth()->user()->branchIds();
            foreach ($lines as $line) {
                if (! in_array((int) $line['branch_id'], $allowedBranchIds, true)) {
                    throw ValidationException::withMessages([
                        'restock_allocations' => ['You can only restock to your assigned branches.'],
                    ]);
                }
            }
        }

        try {
            $purchases = app(StockPurchaseService::class)->createDistributed([
                'product_id' => (int) $data['product_id'],
                'unit_cost' => (float) $data['unit_cost'],
                'sale_price' => (float) $data['sale_price'],
                'bank_account_id' => (int) $data['bank_account_id'],
                'date' => $data['date'] ?? now()->toDateString(),
                'vendor' => $data['vendor'] ?? null,
                'lines' => $lines,
            ]);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['bank_account_id'][0]
                ?? $exception->errors()['lines'][0]
                ?? 'The selected account does not have enough balance.';

            Notification::make()
                ->danger()
                ->title('Insufficient account balance')
                ->body($message)
                ->send();

            throw $exception;
        }

        $first = $purchases[0];
        if (count($purchases) > 1) {
            Notification::make()
                ->success()
                ->title('Restock recorded')
                ->body(count($purchases).' branch allocations; one payment from the selected account.')
                ->send();
        }

        return $first;
    }
}
