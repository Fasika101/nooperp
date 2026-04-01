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
        $bulkRaw = trim((string) ($data['bulk_values'] ?? ''));
        unset($data['bulk_values']);

        if ($bulkRaw !== '') {
            $names = ProductOption::parseBulkNames($bulkRaw);
            $type = $data['type'] ?? null;

            if (! is_string($type) || $type === '') {
                throw ValidationException::withMessages([
                    'type' => ['Choose a type for these options.'],
                ]);
            }

            if ($names === []) {
                throw ValidationException::withMessages([
                    'bulk_values' => ['Enter at least one non-empty value.'],
                ]);
            }

            $created = 0;
            $skipped = 0;
            $last = null;

            foreach ($names as $name) {
                $option = ProductOption::query()->firstOrCreate([
                    'type' => $type,
                    'name' => $name,
                ]);

                $last = $option;

                if ($option->wasRecentlyCreated) {
                    $created++;
                } else {
                    $skipped++;
                }
            }

            $label = ProductOption::getTypeOptions()[$type] ?? $type;

            if ($created === 0 && $skipped > 0) {
                Notification::make()
                    ->warning()
                    ->title('No new options added')
                    ->body("All {$skipped} values already exist for {$label}.")
                    ->send();
            } else {
                Notification::make()
                    ->success()
                    ->title($created.' '.$label.' option(s) added')
                    ->body($skipped > 0 ? "{$skipped} already existed and were skipped." : null)
                    ->send();
            }

            assert($last instanceof ProductOption);

            return $last;
        }

        $duplicate = ProductOption::query()
            ->where('type', $data['type'] ?? '')
            ->where('name', $data['name'] ?? '')
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => ['This value already exists for the selected type.'],
            ]);
        }

        return parent::handleRecordCreation($data);
    }
}
