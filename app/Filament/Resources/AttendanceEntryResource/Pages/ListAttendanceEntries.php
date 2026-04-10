<?php

namespace App\Filament\Resources\AttendanceEntryResource\Pages;

use App\Filament\Resources\AttendanceEntryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceEntries extends ListRecords
{
    protected static string $resource = AttendanceEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
