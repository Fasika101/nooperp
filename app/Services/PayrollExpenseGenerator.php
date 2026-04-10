<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PayrollExpenseGenerator
{
    /**
     * Create salary expenses for all eligible employees for the given pay date (same calendar month).
     *
     * @return array{created: int, skipped: int, messages: list<string>}
     */
    public function generate(Carbon $payDate, bool $skipIfAlreadyInMonth = true, ?User $user = null): array
    {
        $user ??= auth()->user();
        $salariesTypeId = ExpenseType::salariesTypeId();

        if (! $salariesTypeId) {
            return [
                'created' => 0,
                'skipped' => 0,
                'messages' => ['Expense type “Salaries” is missing. Run ExpenseTypeSeeder or create it.'],
            ];
        }

        $created = 0;
        $skipped = 0;
        $messages = [];

        $employees = Employee::query()
            ->whereIn('employment_status', [
                Employee::STATUS_ACTIVE,
                Employee::STATUS_PROBATION,
                Employee::STATUS_ON_LEAVE,
            ])
            ->where('base_salary', '>', 0)
            ->when(
                $user?->isBranchRestricted(),
                fn ($q) => $q->where('branch_id', $user->branch_id),
            )
            ->orderBy('full_name')
            ->get();

        DB::transaction(function () use ($employees, $payDate, $salariesTypeId, $skipIfAlreadyInMonth, &$created, &$skipped, &$messages): void {
            foreach ($employees as $employee) {
                if ($skipIfAlreadyInMonth) {
                    $exists = Expense::query()
                        ->where('employee_id', $employee->id)
                        ->where('expense_type_id', $salariesTypeId)
                        ->whereYear('date', $payDate->year)
                        ->whereMonth('date', $payDate->month)
                        ->exists();

                    if ($exists) {
                        $skipped++;

                        continue;
                    }
                }

                if (! $employee->branch_id) {
                    $messages[] = "Skipped {$employee->full_name}: no branch on employee profile.";
                    $skipped++;

                    continue;
                }

                $bank = BankAccount::getDefaultAccountForBranch($employee->branch_id);
                if (! $bank) {
                    $messages[] = "Skipped {$employee->full_name}: no bank account for branch.";
                    $skipped++;

                    continue;
                }

                Expense::query()->create([
                    'date' => $payDate->toDateString(),
                    'amount' => $employee->base_salary,
                    'expense_type_id' => $salariesTypeId,
                    'bank_account_id' => $bank->id,
                    'branch_id' => $employee->branch_id,
                    'employee_id' => $employee->id,
                    'vendor' => $employee->full_name,
                    'description' => __('Monthly payroll (:month)', ['month' => $payDate->format('F Y')]),
                ]);

                $created++;
            }
        });

        return compact('created', 'skipped', 'messages');
    }
}
