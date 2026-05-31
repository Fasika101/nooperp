<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;

class PayrollExpenseGenerator
{
    public const STATUS_READY = 'ready';

    public const STATUS_SKIPPED_EXISTING = 'skipped_existing';

    public const STATUS_SKIPPED_NO_BRANCH = 'skipped_no_branch';

    public const STATUS_SKIPPED_NO_BANK = 'skipped_no_bank';

    public const STATUS_SKIPPED_NOT_IN_MONTH = 'skipped_not_in_month';

    public const STATUS_SKIPPED_BANK_UNAVAILABLE = 'skipped_bank_unavailable';

    public function __construct(
        protected PayrollProration $proration,
    ) {}

    /**
     * Build a payroll preview report without creating expenses.
     *
     * @return array{
     *     pay_date: string,
     *     pay_month: string,
     *     currency: string,
     *     salaries_type_missing: bool,
     *     error_message: ?string,
     *     lines: list<array{
     *         employee_id: int,
     *         full_name: string,
     *         branch: ?string,
     *         bank_account: ?string,
     *         amount: float,
     *         base_salary: float,
     *         days_worked: int,
     *         days_in_month: int,
     *         is_prorated: bool,
     *         proration_note: ?string,
     *         status: string,
     *         status_label: string
     *     }>,
     *     ready_count: int,
     *     ready_total: float,
     *     skipped_count: int,
     *     bank_account_id: ?int,
     *     bank_account_name: ?string,
     *     bank_account_balance: ?float,
     *     has_sufficient_balance: bool
     * }
     */
    public function preview(
        Carbon $payDate,
        bool $skipIfAlreadyInMonth = true,
        ?User $user = null,
        ?int $bankAccountId = null,
    ): array {
        $user ??= auth()->user();
        $salariesTypeId = ExpenseType::salariesTypeId();

        if (! $salariesTypeId) {
            return $this->emptyReport($payDate, salariesTypeMissing: true, errorMessage: 'Expense type “Salaries” is missing. Run ExpenseTypeSeeder or create it.');
        }

        $lines = $this->getEligibleEmployees($user, $payDate)
            ->map(fn (Employee $employee): array => $this->resolveLine($employee, $payDate, $salariesTypeId, $skipIfAlreadyInMonth, $bankAccountId))
            ->values()
            ->all();

        $readyLines = collect($lines)->where('status', self::STATUS_READY);
        $readyTotal = (float) $readyLines->sum('amount');
        $bankAccount = $bankAccountId ? BankAccount::query()->find($bankAccountId) : null;

        return [
            'pay_date' => $payDate->toDateString(),
            'pay_month' => $payDate->format('F Y'),
            'currency' => Setting::getDefaultCurrency(),
            'salaries_type_missing' => false,
            'error_message' => null,
            'lines' => $lines,
            'ready_count' => $readyLines->count(),
            'ready_total' => $readyTotal,
            'skipped_count' => collect($lines)->where('status', '!=', self::STATUS_READY)->count(),
            'bank_account_id' => $bankAccount?->id,
            'bank_account_name' => $bankAccount?->name,
            'bank_account_balance' => $bankAccount ? (float) $bankAccount->current_balance : null,
            'has_sufficient_balance' => $bankAccount
                ? (float) $bankAccount->current_balance >= $readyTotal
                : true,
        ];
    }

    /**
     * Create salary expenses for all eligible employees for the given pay date (same calendar month).
     *
     * @return array{created: int, skipped: int, messages: list<string>}
     */
    public function generate(
        Carbon $payDate,
        bool $skipIfAlreadyInMonth = true,
        ?User $user = null,
        ?int $bankAccountId = null,
    ): array {
        $user ??= auth()->user();
        $report = $this->preview($payDate, $skipIfAlreadyInMonth, $user, $bankAccountId);

        if ($report['salaries_type_missing']) {
            return [
                'created' => 0,
                'skipped' => 0,
                'messages' => [$report['error_message']],
            ];
        }

        if ($report['ready_count'] === 0) {
            return [
                'created' => 0,
                'skipped' => $report['skipped_count'],
                'messages' => ['No salary expenses are ready to be created with the current settings.'],
            ];
        }

        if ($bankAccountId && ! $report['has_sufficient_balance']) {
            return [
                'created' => 0,
                'skipped' => $report['skipped_count'],
                'messages' => [
                    'Insufficient balance in '.($report['bank_account_name'] ?? 'the selected account')
                    .'. Need '.Setting::getDefaultCurrency().' '
                    .number_format($report['ready_total'], 2)
                    .' but only '
                    .number_format((float) $report['bank_account_balance'], 2)
                    .' is available.',
                ],
            ];
        }

        $salariesTypeId = ExpenseType::salariesTypeId();
        $created = 0;
        $skipped = $report['skipped_count'];
        $messages = [];

        DB::transaction(function () use ($report, $payDate, $salariesTypeId, $bankAccountId, &$created, &$skipped, &$messages): void {
            foreach ($report['lines'] as $line) {
                if ($line['status'] !== self::STATUS_READY) {
                    if (in_array($line['status'], [
                        self::STATUS_SKIPPED_NO_BRANCH,
                        self::STATUS_SKIPPED_NO_BANK,
                        self::STATUS_SKIPPED_BANK_UNAVAILABLE,
                    ], true)) {
                        $messages[] = "Skipped {$line['full_name']}: {$line['status_label']}.";
                    }

                    continue;
                }

                $employee = Employee::query()->find($line['employee_id']);
                if (! $employee) {
                    $skipped++;

                    continue;
                }

                $selectedBankId = (int) ($line['bank_account_id'] ?? $bankAccountId);
                $bank = BankAccount::query()->find($selectedBankId);
                if (! $bank) {
                    $messages[] = "Skipped {$employee->full_name}: pay-from account not found.";
                    $skipped++;

                    continue;
                }

                if ($employee->branch_id && ! $bank->isUsableAtBranch((int) $employee->branch_id)) {
                    $messages[] = "Skipped {$employee->full_name}: pay-from account not available for branch.";
                    $skipped++;

                    continue;
                }

                Expense::query()->create([
                    'date' => $payDate->toDateString(),
                    'amount' => $line['amount'],
                    'expense_type_id' => $salariesTypeId,
                    'bank_account_id' => $bank->id,
                    'branch_id' => $employee->branch_id,
                    'employee_id' => $employee->id,
                    'vendor' => $employee->full_name,
                    'description' => $this->payrollDescription($payDate, $line),
                ]);

                $created++;
            }
        });

        return compact('created', 'skipped', 'messages');
    }

    /**
     * @return EloquentCollection<int, Employee>
     */
    protected function getEligibleEmployees(?User $user, Carbon $payDate): EloquentCollection
    {
        $monthStart = $payDate->copy()->startOfMonth()->toDateString();
        $monthEnd = $payDate->copy()->endOfMonth()->toDateString();

        return Employee::query()
            ->with('branch')
            ->where('base_salary', '>', 0)
            ->where(function ($query) use ($monthStart, $monthEnd): void {
                $query->whereIn('employment_status', [
                    Employee::STATUS_ACTIVE,
                    Employee::STATUS_PROBATION,
                    Employee::STATUS_ON_LEAVE,
                ])->orWhere(function ($query) use ($monthStart, $monthEnd): void {
                    $query->where('employment_status', Employee::STATUS_TERMINATED)
                        ->whereNotNull('termination_date')
                        ->whereBetween('termination_date', [$monthStart, $monthEnd]);
                });
            })
            ->where(function ($query) use ($monthEnd): void {
                $query->whereNull('hire_date')
                    ->orWhereDate('hire_date', '<=', $monthEnd);
            })
            ->when(
                $user?->isBranchRestricted(),
                fn ($q) => $q->whereIn('branch_id', $user->branchIds()),
            )
            ->orderBy('full_name')
            ->get();
    }

    /**
     * @return array{
     *     employee_id: int,
     *     full_name: string,
     *     branch: ?string,
     *     bank_account: ?string,
     *     amount: float,
     *     base_salary: float,
     *     days_worked: int,
     *     days_in_month: int,
     *     is_prorated: bool,
     *     proration_note: ?string,
     *     bank_account_id: ?int,
     *     status: string,
     *     status_label: string
     * }
     */
    protected function resolveLine(
        Employee $employee,
        Carbon $payDate,
        int $salariesTypeId,
        bool $skipIfAlreadyInMonth,
        ?int $bankAccountId = null,
    ): array {
        $proration = $this->proration->forEmployeeInMonth($employee, $payDate);

        $base = [
            'employee_id' => $employee->id,
            'full_name' => $employee->full_name,
            'branch' => $employee->branch?->name,
            'bank_account' => null,
            'bank_account_id' => null,
            'amount' => $proration['amount'],
            'base_salary' => $proration['base_salary'],
            'days_worked' => $proration['days_worked'],
            'days_in_month' => $proration['days_in_month'],
            'is_prorated' => $proration['is_prorated'],
            'proration_note' => $proration['note'],
            'status' => self::STATUS_READY,
            'status_label' => $proration['is_prorated'] ? 'Will create (prorated)' : 'Will create',
        ];

        if (! $proration['eligible']) {
            return [
                ...$base,
                'status' => self::STATUS_SKIPPED_NOT_IN_MONTH,
                'status_label' => $proration['ineligible_reason'] ?? 'Not payable this month',
            ];
        }

        if ($skipIfAlreadyInMonth) {
            $exists = Expense::query()
                ->where('employee_id', $employee->id)
                ->where('expense_type_id', $salariesTypeId)
                ->whereYear('date', $payDate->year)
                ->whereMonth('date', $payDate->month)
                ->exists();

            if ($exists) {
                return [
                    ...$base,
                    'status' => self::STATUS_SKIPPED_EXISTING,
                    'status_label' => 'Already paid this month',
                ];
            }
        }

        if (! $employee->branch_id) {
            return [
                ...$base,
                'status' => self::STATUS_SKIPPED_NO_BRANCH,
                'status_label' => 'No branch assigned',
            ];
        }

        $bank = $this->resolvePayFromAccount($employee, $bankAccountId);
        if ($bank === false) {
            return [
                ...$base,
                'status' => self::STATUS_SKIPPED_NO_BANK,
                'status_label' => $bankAccountId
                    ? 'Pay-from account not found'
                    : 'No bank account for branch',
            ];
        }

        if ($bank === null) {
            return [
                ...$base,
                'status' => self::STATUS_SKIPPED_BANK_UNAVAILABLE,
                'status_label' => 'Pay-from account not available for branch',
            ];
        }

        return [
            ...$base,
            'bank_account' => $bank->name,
            'bank_account_id' => $bank->id,
        ];
    }

    /**
     * @return BankAccount|null|false false when no account exists at all
     */
    protected function resolvePayFromAccount(Employee $employee, ?int $bankAccountId): BankAccount|null|false
    {
        if ($bankAccountId) {
            $bank = BankAccount::query()->find($bankAccountId);

            if (! $bank) {
                return false;
            }

            if ($employee->branch_id && ! $bank->isUsableAtBranch((int) $employee->branch_id)) {
                return null;
            }

            return $bank;
        }

        $bank = BankAccount::getDefaultAccountForBranch($employee->branch_id);

        return $bank ?: false;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    protected function payrollDescription(Carbon $payDate, array $line): string
    {
        $month = $payDate->format('F Y');

        if ($line['is_prorated'] ?? false) {
            return __('Monthly payroll (:month) — :note', [
                'month' => $month,
                'note' => $line['proration_note'] ?? __('Prorated'),
            ]);
        }

        return __('Monthly payroll (:month)', ['month' => $month]);
    }

    /**
     * @return array{
     *     pay_date: string,
     *     pay_month: string,
     *     currency: string,
     *     salaries_type_missing: bool,
     *     error_message: ?string,
     *     lines: list<array<string, mixed>>,
     *     ready_count: int,
     *     ready_total: float,
     *     skipped_count: int
     * }
     */
    protected function emptyReport(Carbon $payDate, bool $salariesTypeMissing, ?string $errorMessage): array
    {
        return [
            'pay_date' => $payDate->toDateString(),
            'pay_month' => $payDate->format('F Y'),
            'currency' => Setting::getDefaultCurrency(),
            'salaries_type_missing' => $salariesTypeMissing,
            'error_message' => $errorMessage,
            'lines' => [],
            'ready_count' => 0,
            'ready_total' => 0.0,
            'skipped_count' => 0,
            'bank_account_id' => null,
            'bank_account_name' => null,
            'bank_account_balance' => null,
            'has_sufficient_balance' => true,
        ];
    }
}
