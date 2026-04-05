<?php

namespace App\Filament\Resources\ProductOptionResource\Pages;

use App\Filament\Resources\ProductOptionResource;
use App\Models\ProductOption;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateProductOption extends CreateRecord
{
    protected static string $resource = ProductOptionResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $type = $data['type'] ?? null;
        if (! is_string($type) || $type === '') {
            throw ValidationException::withMessages([
                'type' => ['Choose a type for these options.'],
            ]);
        }

        $raw = $data['option_values'] ?? [];
        unset($data['option_values']);

        $names = ProductOption::normalizeNamesFromFragments(is_array($raw) ? $raw : []);

        if ($names === []) {
            throw ValidationException::withMessages([
                'option_values' => ['Add at least one value, or remove empty rows.'],
            ]);
        }

        if (count($names) > 1) {
            $result = ProductOption::firstOrCreateManyForType($type, $names);
            $label = ProductOption::getTypeOptions()[$type] ?? $type;

            if ($result['created'] === 0 && $result['skipped'] > 0) {
                Notification::make()
                    ->warning()
                    ->title('No new options added')
                    ->body("All {$result['skipped']} values already exist for {$label}.")
                    ->send();
            } else {
                Notification::make()
                    ->success()
                    ->title($result['created'].' '.$label.' option(s) added')
                    ->body($result['skipped'] > 0 ? "{$result['skipped']} already existed and were skipped." : null)
                    ->send();
            }

            return $result['last'];
        }

        $duplicate = ProductOption::query()
            ->where('type', $type)
            ->where('name', $names[0])
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'option_values' => ['This value already exists for the selected type.'],
            ]);
        }

        $data['name'] = $names[0];

        return parent::handleRecordCreation($data);
    }
}
