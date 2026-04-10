<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Services\PayrollTaxCalculator;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $gross = isset($data['base_salary']) ? (float) $data['base_salary'] : 0;
        $computed = PayrollTaxCalculator::calculate($gross);
        $data['payroll_tax_amount'] = $computed['tax'];
        $data['net_salary_after_tax'] = $computed['net'];

        return $data;
    }

    protected function resolveRecord(int|string $key): Model
    {
        $record = parent::resolveRecord($key);
        $record->loadMissing('user');

        return $record;
    }
}
