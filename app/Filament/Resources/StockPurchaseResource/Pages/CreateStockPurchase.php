<?php

namespace App\Filament\Resources\StockPurchaseResource\Pages;

use App\Filament\Resources\StockPurchaseResource;
use App\Services\StockPurchaseService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateStockPurchase extends CreateRecord
{
    protected static string $resource = StockPurchaseResource::class;

    public function mount(): void
    {
        parent::mount();
        $productId = request()->query('product_id');
        if (filled($productId)) {
            $this->form->fill(['product_id' => $productId]);
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(StockPurchaseService::class)->create($data);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['bank_account_id'][0] ?? 'The selected account does not have enough balance.';

            Notification::make()
                ->danger()
                ->title('Insufficient account balance')
                ->body($message)
                ->send();

            throw $exception;
        }
    }
}
