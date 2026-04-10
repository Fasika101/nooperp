<?php

namespace App\Filament\Resources\AttendanceEntryResource\Pages;

use App\Filament\Resources\AttendanceEntryResource;
use App\Models\AttendanceEntry;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditAttendanceEntry extends EditRecord
{
    protected static string $resource = AttendanceEntryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $id = $this->getRecord()->getKey();

        $duplicate = AttendanceEntry::query()
            ->where('employee_id', $data['employee_id'])
            ->whereDate('work_date', $data['work_date'])
            ->whereKeyNot($id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'work_date' => __('Another attendance row already exists for this employee on this date.'),
            ]);
        }

        return $data;
    }
}
