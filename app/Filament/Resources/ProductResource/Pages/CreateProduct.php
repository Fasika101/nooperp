<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Services\ProductCreationService;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * When using a custom create handler, dehydrated state can omit the image even though the
     * upload is still present on the form. Merge from raw Livewire / schema state when needed.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->mergeProductImageFromFormState($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $data = $this->mergeProductImageFromFormState($data);

        try {
            return app(ProductCreationService::class)->create($data);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['bank_account_id'][0]
                ?? $exception->errors()['initial_stock_bank_account_id'][0]
                ?? 'The selected account does not have enough balance.';

            Notification::make()
                ->danger()
                ->title('Insufficient account balance')
                ->body($message)
                ->send();

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergeProductImageFromFormState(array $data): array
    {
        if (filled($data['image'] ?? null)) {
            return $data;
        }

        $rawState = $this->form->getRawState();

        $candidates = [
            data_get($this->data, 'image'),
            data_get($rawState, 'data.image'),
            data_get($rawState, 'image'),
        ];

        foreach ($candidates as $raw) {
            if (filled($raw)) {
                $data['image'] = $raw;

                break;
            }
        }

        return $data;
    }
}
