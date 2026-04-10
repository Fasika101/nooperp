<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductOption;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data = parent::mutateFormDataBeforeFill($data);

        $attached = $this->record->attachedProductOptions()->get();
        $data['size_option_ids'] = $attached->where('type', ProductOption::TYPE_SIZE)->pluck('id')->values()->all();
        $data['color_option_ids'] = $attached->where('type', ProductOption::TYPE_COLOR)->pluck('id')->values()->all();

        return $data;
    }

    protected function afterSave(): void
    {
        $state = $this->form->getState();
        $sizeIds = Product::validatedOptionIdsForType((array) ($state['size_option_ids'] ?? []), ProductOption::TYPE_SIZE);
        $colorIds = Product::validatedOptionIdsForType((array) ($state['color_option_ids'] ?? []), ProductOption::TYPE_COLOR);

        $this->record->attachedProductOptions()->sync(array_values(array_unique(array_merge($sizeIds, $colorIds))));

        $this->record->updateQuietly([
            'size_option_id' => $sizeIds[0] ?? null,
            'color_option_id' => $colorIds[0] ?? null,
        ]);
    }
}
