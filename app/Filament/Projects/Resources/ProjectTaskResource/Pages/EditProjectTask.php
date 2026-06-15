<?php

namespace App\Filament\Projects\Resources\ProjectTaskResource\Pages;

use App\Filament\Projects\Resources\ProjectTaskResource;
use App\Models\ProjectTask;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProjectTask extends EditRecord
{
    protected static string $resource = ProjectTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Populate virtual multi-select fields (assignees, labels) before the form loads.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ProjectTask $record */
        $record = $this->getRecord();

        $data['assignee_ids'] = $record->assignees()->pluck('users.id')->all();
        $data['label_ids']    = $record->labels()->pluck('project_labels.id')->all();

        return $data;
    }

    /**
     * Sync many-to-many relations after the main record is saved.
     */
    protected function afterSave(): void
    {
        /** @var ProjectTask $record */
        $record = $this->getRecord();

        $record->assignees()->sync($this->data['assignee_ids'] ?? []);
        $record->labels()->sync($this->data['label_ids'] ?? []);
    }
}
