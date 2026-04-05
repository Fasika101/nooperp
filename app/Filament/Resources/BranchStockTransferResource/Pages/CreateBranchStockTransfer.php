<?php

declare(strict_types=1);

namespace App\Filament\Resources\BranchStockTransferResource\Pages;

use App\Filament\Resources\BranchStockTransferResource;
use App\Services\BranchStockTransferService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBranchStockTransfer extends CreateRecord
{
    protected static string $resource = BranchStockTransferResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(BranchStockTransferService::class)->transfer([
            ...$data,
            'user_id' => auth()->id(),
        ]);
    }
}
