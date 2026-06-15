<?php

namespace App\Filament\Projects\Resources\CalendarEventResource\Pages;

use App\Filament\Projects\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCalendarEvent extends EditRecord
{
    protected static string $resource = CalendarEventResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var CalendarEvent $record */
        $record = $this->getRecord();
        $data['attendee_ids'] = $record->attendees()->pluck('users.id')->all();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (empty($data['start_time'])) {
            $data['start_time'] = null;
            $data['end_time']   = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->attendees()->sync($this->data['attendee_ids'] ?? []);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => (int) $this->record->created_by === (int) auth()->id()),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return CalendarEventResource::getUrl('index');
    }
}
