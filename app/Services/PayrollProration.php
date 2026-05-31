<?php

namespace App\Services;

use App\Models\Employee;
use Carbon\Carbon;

class PayrollProration
{
    /**
     * @return array{
     *     amount: float,
     *     base_salary: float,
     *     days_worked: int,
     *     days_in_month: int,
     *     is_prorated: bool,
     *     work_start: ?string,
     *     work_end: ?string,
     *     note: ?string,
     *     eligible: bool,
     *     ineligible_reason: ?string
     * }
     */
    public function forEmployeeInMonth(Employee $employee, Carbon $payDate): array
    {
        $monthStart = $payDate->copy()->startOfMonth()->startOfDay();
        $monthEnd = $payDate->copy()->endOfMonth()->startOfDay();
        $daysInMonth = $monthStart->daysInMonth;
        $baseSalary = (float) $employee->base_salary;

        if ($baseSalary <= 0) {
            return $this->ineligible($baseSalary, $daysInMonth, 'No base salary set');
        }

        if ($employee->hire_date && $employee->hire_date->gt($monthEnd)) {
            return $this->ineligible($baseSalary, $daysInMonth, 'Hire date is after this month');
        }

        if ($employee->termination_date && $employee->termination_date->lt($monthStart)) {
            return $this->ineligible($baseSalary, $daysInMonth, 'Left before this month');
        }

        $workStart = $employee->hire_date
            ? Carbon::parse($employee->hire_date)->startOfDay()->max($monthStart)
            : $monthStart;

        if ($employee->salary_effective_date) {
            $effective = Carbon::parse($employee->salary_effective_date)->startOfDay();
            if ($effective->gt($workStart)) {
                $workStart = $effective->max($monthStart);
            }
        }

        $workEnd = $employee->termination_date
            ? Carbon::parse($employee->termination_date)->startOfDay()->min($monthEnd)
            : $monthEnd;

        if ($workStart->gt($workEnd)) {
            return $this->ineligible($baseSalary, $daysInMonth, 'No payable days in this month');
        }

        $daysWorked = (int) $workStart->diffInDays($workEnd) + 1;
        $isProrated = $daysWorked < $daysInMonth;
        $amount = $isProrated
            ? round($baseSalary * ($daysWorked / $daysInMonth), 2)
            : $baseSalary;

        $note = $isProrated
            ? __('Prorated: :worked of :total days', ['worked' => $daysWorked, 'total' => $daysInMonth])
            : null;

        return [
            'amount' => $amount,
            'base_salary' => $baseSalary,
            'days_worked' => $daysWorked,
            'days_in_month' => $daysInMonth,
            'is_prorated' => $isProrated,
            'work_start' => $workStart->toDateString(),
            'work_end' => $workEnd->toDateString(),
            'note' => $note,
            'eligible' => true,
            'ineligible_reason' => null,
        ];
    }

    /**
     * @return array{
     *     amount: float,
     *     base_salary: float,
     *     days_worked: int,
     *     days_in_month: int,
     *     is_prorated: bool,
     *     work_start: ?string,
     *     work_end: ?string,
     *     note: ?string,
     *     eligible: bool,
     *     ineligible_reason: ?string
     * }
     */
    protected function ineligible(float $baseSalary, int $daysInMonth, string $reason): array
    {
        return [
            'amount' => 0.0,
            'base_salary' => $baseSalary,
            'days_worked' => 0,
            'days_in_month' => $daysInMonth,
            'is_prorated' => false,
            'work_start' => null,
            'work_end' => null,
            'note' => null,
            'eligible' => false,
            'ineligible_reason' => $reason,
        ];
    }
}
