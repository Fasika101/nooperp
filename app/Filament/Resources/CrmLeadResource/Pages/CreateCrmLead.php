<?php

namespace App\Filament\Resources\CrmLeadResource\Pages;

use App\Filament\Resources\CrmLeadResource;
use App\Models\CrmLeadStage;
use Filament\Resources\Pages\CreateRecord;

class CreateCrmLead extends CreateRecord
{
    protected static string $resource = CrmLeadResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['crm_lead_stage_id'])) {
            $data['crm_lead_stage_id'] = CrmLeadStage::query()->orderBy('position')->value('id');
        }

        return $data;
    }
}
