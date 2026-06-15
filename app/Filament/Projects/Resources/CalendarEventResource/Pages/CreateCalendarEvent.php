<?php

namespace App\Filament\Projects\Resources\CalendarEventResource\Pages;

use App\Filament\Projects\Resources\CalendarEventResource;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;

class CreateCalendarEvent extends CreateRecord
{
    protected static string $resource = CalendarEventResource::class;

    public function mount(): void
    {
        parent::mount();

        // When opened by clicking a calendar day, pre-fill start_date
        if ($date = request()->query('date')) {
            $this->form->fill([
                'start_date' => $date,
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        // If all_day is checked, clear the time fields
        if (empty($data['start_time'])) {
            $data['start_time'] = null;
            $data['end_time']   = null;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $attendeeIds = $this->data['attendee_ids'] ?? [];
        $this->record->attendees()->sync($attendeeIds);
    }

    protected function getRedirectUrl(): string
    {
        return CalendarEventResource::getUrl('index');
    }
}
