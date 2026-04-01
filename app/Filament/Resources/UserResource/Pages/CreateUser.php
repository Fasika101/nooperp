<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected array $rolesToSync = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->rolesToSync = $data['roles'] ?? [];
        unset($data['roles']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! empty($this->rolesToSync)) {
            $this->record->syncRoles($this->rolesToSync);
        }
    }
}
