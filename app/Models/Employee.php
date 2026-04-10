<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    public const EMPLOYMENT_TYPE_FULL_TIME = 'full_time';

    public const EMPLOYMENT_TYPE_PART_TIME = 'part_time';

    public const EMPLOYMENT_TYPE_CONTRACT = 'contract';

    public const EMPLOYMENT_TYPE_INTERN = 'intern';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PROBATION = 'probation';

    public const STATUS_ON_LEAVE = 'on_leave';

    public const STATUS_TERMINATED = 'terminated';

    public const PAY_MONTHLY = 'monthly';

    public const PAY_WEEKLY = 'weekly';

    public const PAY_BIWEEKLY = 'biweekly';

    public const PAY_DAILY = 'daily';

    protected $fillable = [
        'employee_code',
        'full_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'address',
        'national_id',
        'emergency_contact_name',
        'emergency_contact_phone',
        'department_id',
        'job_position_id',
        'manager_id',
        'branch_id',
        'employment_type',
        'employment_status',
        'hire_date',
        'probation_end_date',
        'termination_date',
        'termination_notes',
        'user_id',
        'base_salary',
        'salary_effective_date',
        'pay_frequency',
        'salary_currency',
        'bank_name',
        'bank_account_no',
        'payroll_tax_id',
        'hours_per_day',
        'days_per_week',
        'hourly_rate',
        'payroll_tax_amount',
        'net_salary_after_tax',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'hire_date' => 'date',
            'probation_end_date' => 'date',
            'termination_date' => 'date',
            'salary_effective_date' => 'date',
            'base_salary' => 'decimal:2',
            'hours_per_day' => 'decimal:2',
            'days_per_week' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'payroll_tax_amount' => 'decimal:2',
            'net_salary_after_tax' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Employee $employee): void {
            if (blank($employee->employee_code)) {
                $next = ((int) static::query()->max('id')) + 1;
                $employee->employee_code = 'EMP-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT);
            }
            if (blank($employee->salary_currency)) {
                $employee->salary_currency = Setting::getDefaultCurrency();
            }
        });
    }

    public static function employmentTypeOptions(): array
    {
        return [
            self::EMPLOYMENT_TYPE_FULL_TIME => 'Full-time',
            self::EMPLOYMENT_TYPE_PART_TIME => 'Part-time',
            self::EMPLOYMENT_TYPE_CONTRACT => 'Contract',
            self::EMPLOYMENT_TYPE_INTERN => 'Intern',
        ];
    }

    public static function employmentStatusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PROBATION => 'Probation',
            self::STATUS_ON_LEAVE => 'On leave',
            self::STATUS_TERMINATED => 'Terminated',
        ];
    }

    public static function payFrequencyOptions(): array
    {
        return [
            self::PAY_MONTHLY => 'Monthly',
            self::PAY_WEEKLY => 'Weekly',
            self::PAY_BIWEEKLY => 'Bi-weekly',
            self::PAY_DAILY => 'Daily',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function jobPosition(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function attendanceEntries(): HasMany
    {
        return $this->hasMany(AttendanceEntry::class);
    }

    /**
     * Expenses linked to this employee (typically salary / payroll lines).
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
