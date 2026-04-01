<?php

namespace Database\Seeders;

use App\Models\CrmDealStage;
use App\Models\CrmLeadStage;
use App\Models\ProjectTaskStage;
use Illuminate\Database\Seeder;

class CrmProjectStagesSeeder extends Seeder
{
    public function run(): void
    {
        if (CrmLeadStage::query()->exists()) {
            return;
        }

        $leadStages = ['New', 'Contacted', 'Qualified', 'Unqualified', 'Converted'];
        foreach ($leadStages as $i => $name) {
            CrmLeadStage::query()->create(['name' => $name, 'position' => $i + 1]);
        }

        $dealStages = ['Discovery', 'Proposal', 'Negotiation', 'Won', 'Lost'];
        foreach ($dealStages as $i => $name) {
            CrmDealStage::query()->create(['name' => $name, 'position' => $i + 1]);
        }

        $taskStages = ['To do', 'In progress', 'Review', 'Done'];
        foreach ($taskStages as $i => $name) {
            ProjectTaskStage::query()->create(['name' => $name, 'position' => $i + 1]);
        }
    }
}
