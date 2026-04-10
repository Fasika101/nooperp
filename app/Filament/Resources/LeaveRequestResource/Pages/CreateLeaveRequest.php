<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Filament\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use Filament\Resources\Pages\CreateRecord;

class CreateLeaveRequest extends CreateRecord
{
    protected static string $resource = LeaveRequestResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = LeaveRequest::STATUS_PENDING;
        $data['reviewed_by'] = null;
        $data['reviewed_at'] = null;
        $data['review_notes'] = null;

        return $data;
    }
}
