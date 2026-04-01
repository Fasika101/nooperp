<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;

    /** @var list<int|string>|null */
    protected ?array $pendingMemberIds = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingMemberIds = $data['member_ids'] ?? [];
        unset($data['member_ids']);
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $ids = array_unique(array_filter(array_merge(
            $this->pendingMemberIds ?? [],
            [auth()->id()]
        )));
        foreach ($ids as $uid) {
            $this->record->members()->syncWithoutDetaching([
                $uid => ['role' => 'member'],
            ]);
        }
    }
}
