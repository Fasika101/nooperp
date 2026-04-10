<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\ExpenseType;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    protected static string $resource = ExpenseResource::class;

    public function mount(): void
    {
        parent::mount();
        $fill = [];
        if (filled(request()->query('expense_type_id'))) {
            $fill['expense_type_id'] = (int) request()->query('expense_type_id');
        }
        if (filled(request()->query('employee_id'))) {
            $employeeId = (int) request()->query('employee_id');
            $fill['employee_id'] = $employeeId;
            $employee = Employee::query()->find($employeeId);
            if ($employee && (float) $employee->base_salary > 0) {
                $fill['amount'] = $employee->base_salary;
                $fill['vendor'] = $employee->full_name;
                if ($employee->branch_id) {
                    $fill['branch_id'] = $employee->branch_id;
                }
                $bankId = BankAccount::getDefaultAccountForBranch($employee->branch_id)?->id;
                if ($bankId) {
                    $fill['bank_account_id'] = $bankId;
                }
            }
        }
        if ($fill !== []) {
            $this->form->fill($fill);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $sid = ExpenseType::salariesTypeId();
        if (! $sid || (int) ($data['expense_type_id'] ?? 0) !== $sid) {
            $data['employee_id'] = null;
        }

        return $data;
    }
}
