<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductOptions\Pages;

use App\Filament\Resources\ProductOptions\ProductOptionTypeResource;
use App\Models\ProductOption;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

abstract class CreateProductOptionTypeRecord extends CreateRecord
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /** @var class-string<ProductOptionTypeResource> $resource */
        $resource = static::getResource();
        $data['type'] = $resource::optionType();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var class-string<ProductOptionTypeResource> $resource */
        $resource = static::getResource();
        $type = $resource::optionType();

        $raw = $data['option_values'] ?? '';
        unset($data['option_values']);

        $names = ProductOption::parseBulkNames(is_string($raw) ? $raw : '');

        if ($names === []) {
            throw ValidationException::withMessages([
                'option_values' => ['Enter at least one value (separate with commas or new lines).'],
            ]);
        }

        if (count($names) > 1) {
            $result = ProductOption::firstOrCreateManyForType($type, $names);
            $this->sendMultiCreateNotification($type, $result['created'], $result['skipped']);

            return $result['last'];
        }

        $duplicate = ProductOption::query()
            ->where('type', $type)
            ->where('name', $names[0])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'option_values' => ['This value already exists for this option type.'],
            ]);
        }

        $data['name'] = $names[0];

        return parent::handleRecordCreation($data);
    }

    private function sendMultiCreateNotification(string $type, int $created, int $skipped): void
    {
        $label = ProductOption::getTypeOptions()[$type] ?? $type;

        if ($created === 0 && $skipped > 0) {
            Notification::make()
                ->warning()
                ->title('No new options added')
                ->body("All {$skipped} values already exist for {$label}.")
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title($created.' '.$label.' option(s) added')
            ->body($skipped > 0 ? "{$skipped} already existed and were skipped." : null)
            ->send();
    }
}
