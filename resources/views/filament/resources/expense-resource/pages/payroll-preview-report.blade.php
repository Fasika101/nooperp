@php
    use App\Services\PayrollExpenseGenerator;
    use Illuminate\Support\Number;

    $currency = $report['currency'] ?? 'ETB';
@endphp

<div class="space-y-5">
    @if (($report['salaries_type_missing'] ?? false) || filled($report['error_message'] ?? null))
        <div class="rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-300">
            {{ $report['error_message'] }}
        </div>
    @else
        <div class="rounded-xl border border-primary-200 bg-primary-50 px-4 py-3 dark:border-primary-500/30 dark:bg-primary-500/10">
            <div class="text-sm font-semibold text-primary-800 dark:text-primary-200">
                Salary payroll preview — {{ $report['pay_month'] }}
            </div>
            <div class="mt-1 text-xs text-primary-700/80 dark:text-primary-300/80">
                Pay date: {{ \Illuminate\Support\Carbon::parse($report['pay_date'])->toFormattedDateString() }}
                · Salary month: {{ $report['pay_month'] }}
                · Next payroll is due one month after each employee’s last salary payment
                · Partial-month salaries are prorated by days worked
            </div>
            @if ($report['bank_account_name'] ?? null)
                <div class="mt-2 text-xs text-primary-800 dark:text-primary-200">
                    Pay from: <span class="font-semibold">{{ $report['bank_account_name'] }}</span>
                    · Balance: {{ Number::currency($report['bank_account_balance'] ?? 0, $currency) }}
                </div>
            @endif
        </div>

        @if (($report['bank_account_name'] ?? null) && ! ($report['has_sufficient_balance'] ?? true) && ($report['ready_count'] ?? 0) > 0)
            <div class="rounded-xl border border-danger-200 bg-danger-50 px-4 py-3 text-sm text-danger-700 dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-300">
                The selected account does not have enough balance to pay {{ Number::currency($report['ready_total'], $currency) }}.
                Choose a different pay-from account or top up the balance before creating expenses.
            </div>
        @endif

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-xl border border-success-200 bg-success-50 p-4 dark:border-success-500/30 dark:bg-success-500/10">
                <div class="text-xs font-medium uppercase tracking-wide text-success-700 dark:text-success-300">Will pay</div>
                <div class="mt-2 text-2xl font-bold tabular-nums text-success-800 dark:text-success-200">
                    {{ Number::currency($report['ready_total'], $currency) }}
                </div>
                <div class="mt-1 text-xs text-success-700/80 dark:text-success-300/80">
                    {{ number_format($report['ready_count']) }} employee(s)
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Skipped</div>
                <div class="mt-2 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
                    {{ number_format($report['skipped_count']) }}
                </div>
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Already paid, missing branch, or no bank account
                </div>
            </div>

            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                <div class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-300">Total reviewed</div>
                <div class="mt-2 text-2xl font-bold tabular-nums text-amber-900 dark:text-amber-100">
                    {{ number_format(count($report['lines'])) }}
                </div>
                <div class="mt-1 text-xs text-amber-700/80 dark:text-amber-300/80">
                    Eligible employees with salary
                </div>
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
            <table class="w-full min-w-[64rem] text-sm">
                <thead class="bg-emerald-700 text-white">
                    <tr>
                        <th class="px-4 py-3 text-start font-semibold">Employee</th>
                        <th class="px-4 py-3 text-start font-semibold">Branch</th>
                        <th class="px-4 py-3 text-start font-semibold">Pay from account</th>
                        <th class="px-4 py-3 text-end font-semibold">Monthly salary</th>
                        <th class="px-4 py-3 text-center font-semibold">Days worked</th>
                        <th class="px-4 py-3 text-end font-semibold">Pay amount</th>
                        <th class="px-4 py-3 text-start font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                    @forelse ($report['lines'] as $line)
                        @php
                            $isReady = $line['status'] === PayrollExpenseGenerator::STATUS_READY;
                            $rowClass = $isReady
                                ? 'bg-white dark:bg-gray-900'
                                : 'bg-gray-50 dark:bg-white/5';
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                <div>{{ $line['full_name'] }}</div>
                                @if ($line['is_prorated'] ?? false)
                                    <div class="mt-1 text-xs text-sky-700 dark:text-sky-300">{{ $line['proration_note'] }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $line['branch'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $line['bank_account'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-end tabular-nums text-gray-700 dark:text-gray-300">
                                {{ Number::currency($line['base_salary'] ?? $line['amount'], $currency) }}
                            </td>
                            <td class="px-4 py-3 text-center tabular-nums text-gray-700 dark:text-gray-300">
                                {{ $line['days_worked'] ?? '—' }} / {{ $line['days_in_month'] ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-end tabular-nums font-medium text-gray-900 dark:text-white">
                                {{ Number::currency($line['amount'], $currency) }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($isReady)
                                    <span class="inline-flex rounded-full bg-success-100 px-2.5 py-1 text-xs font-medium text-success-800 dark:bg-success-500/20 dark:text-success-300">
                                        {{ $line['status_label'] }}
                                    </span>
                                @elseif (in_array($line['status'], [PayrollExpenseGenerator::STATUS_SKIPPED_EXISTING, PayrollExpenseGenerator::STATUS_SKIPPED_NOT_DUE_YET], true))
                                    <span class="inline-flex rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-300">
                                        {{ $line['status_label'] }}
                                    </span>
                                @else
                                    <span class="inline-flex rounded-full bg-danger-100 px-2.5 py-1 text-xs font-medium text-danger-800 dark:bg-danger-500/20 dark:text-danger-300">
                                        {{ $line['status_label'] }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                No eligible employees with a base salary were found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (($report['lines'] ?? []) !== [])
                    <tfoot class="bg-gray-100 font-semibold dark:bg-white/10">
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-gray-900 dark:text-white">Total to pay</td>
                            <td class="px-4 py-3 text-end tabular-nums text-emerald-700 dark:text-emerald-300">
                                {{ Number::currency($report['ready_total'], $currency) }}
                            </td>
                            <td colspan="2" class="px-4 py-3 text-gray-600 dark:text-gray-400">
                                {{ number_format($report['ready_count']) }} expense(s)
                            </td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        @if (($report['ready_count'] ?? 0) === 0)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                Nothing will be created with the current settings. Adjust the pay date, turn off “skip existing”, or fix employee branch/bank setup.
            </div>
        @endif
    @endif
</div>
