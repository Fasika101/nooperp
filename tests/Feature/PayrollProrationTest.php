<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\ExpenseType;
use App\Models\JobPosition;
use App\Services\PayrollExpenseGenerator;
use App\Services\PayrollProration;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollProrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_prorates_salary_for_employee_hired_mid_month(): void
    {
        $employee = $this->makeEmployee([
            'hire_date' => '2026-03-25',
            'base_salary' => 31000,
        ]);

        $result = app(PayrollProration::class)->forEmployeeInMonth(
            $employee,
            Carbon::parse('2026-03-01'),
        );

        $this->assertTrue($result['eligible']);
        $this->assertTrue($result['is_prorated']);
        $this->assertSame(7, $result['days_worked']);
        $this->assertSame(31, $result['days_in_month']);
        $this->assertSame(7000.0, $result['amount']);
    }

    public function test_pays_full_salary_when_employee_worked_entire_month(): void
    {
        $employee = $this->makeEmployee([
            'hire_date' => '2026-01-10',
            'base_salary' => 20000,
        ]);

        $result = app(PayrollProration::class)->forEmployeeInMonth(
            $employee,
            Carbon::parse('2026-03-01'),
        );

        $this->assertTrue($result['eligible']);
        $this->assertFalse($result['is_prorated']);
        $this->assertSame(31, $result['days_worked']);
        $this->assertSame(20000.0, $result['amount']);
    }

    public function test_prorates_salary_for_employee_terminated_mid_month(): void
    {
        $employee = $this->makeEmployee([
            'hire_date' => '2026-01-01',
            'termination_date' => '2026-03-10',
            'employment_status' => Employee::STATUS_TERMINATED,
            'base_salary' => 31000,
        ]);

        $result = app(PayrollProration::class)->forEmployeeInMonth(
            $employee,
            Carbon::parse('2026-03-01'),
        );

        $this->assertTrue($result['eligible']);
        $this->assertTrue($result['is_prorated']);
        $this->assertSame(10, $result['days_worked']);
        $this->assertSame(10000.0, $result['amount']);
    }

    public function test_payroll_preview_uses_prorated_amount_for_new_hire(): void
    {
        ExpenseType::query()->create([
            'name' => ExpenseType::NAME_SALARIES,
            'is_active' => true,
        ]);

        $branch = Branch::query()->create([
            'name' => 'Payroll Branch',
            'code' => 'payroll-branch',
            'is_active' => true,
            'is_default' => true,
        ]);

        $account = BankAccount::query()->create([
            'name' => 'Cash',
            'branch_id' => $branch->id,
            'currency' => 'ETB',
            'opening_balance' => 50000,
            'current_balance' => 50000,
            'is_default' => true,
        ]);

        $this->makeEmployee([
            'branch_id' => $branch->id,
            'hire_date' => '2026-03-25',
            'base_salary' => 31000,
        ]);

        $report = app(PayrollExpenseGenerator::class)->preview(
            Carbon::parse('2026-03-01'),
            bankAccountId: $account->id,
        );

        $this->assertSame(1, $report['ready_count']);
        $this->assertSame(7000.0, $report['ready_total']);
        $this->assertTrue($report['lines'][0]['is_prorated']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeEmployee(array $overrides = []): Employee
    {
        $department = Department::query()->create(['name' => 'Sales', 'is_active' => true]);
        $position = JobPosition::query()->create([
            'department_id' => $department->id,
            'title' => 'Clerk',
            'is_active' => true,
        ]);

        return Employee::query()->create(array_merge([
            'full_name' => 'Test Employee',
            'department_id' => $department->id,
            'job_position_id' => $position->id,
            'employment_status' => Employee::STATUS_ACTIVE,
            'employment_type' => Employee::EMPLOYMENT_TYPE_FULL_TIME,
            'hire_date' => now()->toDateString(),
            'base_salary' => 10000,
            'pay_frequency' => Employee::PAY_MONTHLY,
        ], $overrides));
    }
}
