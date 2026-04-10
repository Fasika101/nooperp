<?php

namespace App\Filament\Resources\AttendanceEntryResource\Pages;

use App\Filament\Resources\AttendanceEntryResource;
use App\Models\AttendanceEntry;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateAttendanceEntry extends CreateRecord
{
    protected static string $resource = AttendanceEntryResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $exists = AttendanceEntry::query()
            ->where('employee_id', $data['employee_id'])
            ->whereDate('work_date', $data['work_date'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'work_date' => __('An attendance row already exists for this employee on this date.'),
            ]);
        }

        return $data;
    }
}
