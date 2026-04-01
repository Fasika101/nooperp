<?php

namespace App\Filament\Resources\CrmDealResource\Pages;

use App\Filament\Resources\CrmDealResource;
use App\Models\CrmDealStage;
use Filament\Resources\Pages\CreateRecord;

class CreateCrmDeal extends CreateRecord
{
    protected static string $resource = CrmDealResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['crm_deal_stage_id'])) {
            $data['crm_deal_stage_id'] = CrmDealStage::query()->orderBy('position')->value('id');
        }

        return $data;
    }
}
