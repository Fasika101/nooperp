<?php

namespace App\Console\Commands;

use App\Models\OrderItem;
use Illuminate\Console\Command;

class BackfillOrderItemLabelsCommand extends Command
{
    protected $signature = 'order-items:backfill-labels';

    protected $description = 'Backfill missing order item line_label values for receipts and order history';

    public function handle(): int
    {
        $updated = 0;

        OrderItem::query()
            ->where(function ($query): void {
                $query->whereNull('line_label')->orWhere('line_label', '');
            })
            ->with(['product', 'frameSize', 'frameColor'])
            ->orderBy('id')
            ->chunkById(200, function ($items) use (&$updated): void {
                foreach ($items as $item) {
                    $label = $item->buildDisplayLabel();
                    if ($label === '' || $label === 'Sold item') {
                        continue;
                    }

                    $item->updateQuietly(['line_label' => $label]);
                    $updated++;
                }
            });

        $this->info("Updated {$updated} order item label(s).");

        return self::SUCCESS;
    }
}
