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
        // branches handled via relationship; remove from direct fill to avoid mass-assignment noise
        unset($data['branches']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if (! empty($this->rolesToSync)) {
            $this->record->syncRoles($this->rolesToSync);
        }

        $this->syncPrimaryBranch();
    }

    private function syncPrimaryBranch(): void
    {
        $this->record->refresh();
        $first = $this->record->branches()->orderBy('branches.id')->value('branches.id');
        $this->record->updateQuietly(['branch_id' => $first ?? null]);
    }
}
