<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected array $rolesToSync = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->rolesToSync = $data['roles'] ?? [];
        unset($data['roles']);
        unset($data['branches']);

        return $data;
    }

    protected function afterSave(): void
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
