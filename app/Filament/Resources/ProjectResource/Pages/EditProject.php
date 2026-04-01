<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    /** @var list<int|string>|null */
    protected ?array $memberIdsToSync = null;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['member_ids'] = $this->record->members->pluck('id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->memberIdsToSync = $data['member_ids'] ?? [];
        unset($data['member_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        $ids = array_unique(array_filter(array_merge(
            $this->memberIdsToSync ?? [],
            array_filter([$this->record->created_by, auth()->id()])
        )));
        $sync = [];
        foreach ($ids as $id) {
            $sync[$id] = ['role' => 'member'];
        }
        $this->record->members()->sync($sync);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
