<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Services\ExpenseExporter;
use App\Services\PayrollExpenseGenerator;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\Width;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => $this->exportFilteredExpenses()),
            Action::make('generatePayroll')
                ->label('Generate payroll expenses')
                ->icon('heroicon-o-rectangle-stack')
                ->color('gray')
                ->modalHeading('Generate salary expenses')
                ->modalDescription('Choose the pay-from account, review the payroll report, then confirm to create salary expenses.')
                ->modalWidth(Width::SevenExtraLarge)
                ->steps([
                    Step::make('Settings')
                        ->description('Choose pay date, pay-from account, and options')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->schema([
                            DatePicker::make('pay_date')
                                ->label('Pay date')
                                ->helperText('The calendar month of this date is the salary month (e.g. 31 May pays May). The next salary is due one month after the last payment.')
                                ->required()
                                ->default(now())
                                ->native(false),
                            Select::make('bank_account_id')
                                ->label('Pay from account')
                                ->options(fn (): array => $this->payrollBankAccountOptions())
                                ->required()
                                ->searchable()
                                ->preload()
                                ->default(fn (): ?int => BankAccount::getDefaultAccountForBranch(Branch::getDefaultBranch()?->id)?->id
                                    ?? BankAccount::getDefaultAccount()?->id),
                            Toggle::make('skip_existing')
                                ->label('Skip employees already paid for this month or not yet due (one month after last pay)')
                                ->default(true),
                        ]),
                    Step::make('Preview report')
                        ->description('Review totals and each employee before creating expenses')
                        ->icon('heroicon-o-document-chart-bar')
                        ->schema([
                            Placeholder::make('payroll_preview')
                                ->hiddenLabel()
                                ->columnSpanFull()
                                ->dehydrated(false)
                                ->content(function (Get $get): HtmlString {
                                    if (blank($get('pay_date')) || blank($get('bank_account_id'))) {
                                        return new HtmlString('<p class="text-sm text-gray-500 dark:text-gray-400">Choose a pay date and pay-from account in the previous step to preview payroll.</p>');
                                    }

                                    $report = app(PayrollExpenseGenerator::class)->preview(
                                        Carbon::parse($get('pay_date')),
                                        (bool) ($get('skip_existing') ?? true),
                                        bankAccountId: (int) $get('bank_account_id'),
                                    );

                                    return new HtmlString(
                                        view('filament.resources.expense-resource.pages.payroll-preview-report', [
                                            'report' => $report,
                                        ])->render()
                                    );
                                }),
                        ]),
                ])
                ->modalSubmitActionLabel('Create salary expenses')
                ->action(function (array $data): void {
                    try {
                        $generator = app(PayrollExpenseGenerator::class);
                        $bankAccountId = (int) $data['bank_account_id'];
                        $payDate = Carbon::parse($data['pay_date']);
                        $skipExisting = (bool) ($data['skip_existing'] ?? true);

                        $preview = $generator->preview($payDate, $skipExisting, bankAccountId: $bankAccountId);

                        if ($preview['salaries_type_missing']) {
                            Notification::make()
                                ->title(__('Payroll preview failed'))
                                ->body($preview['error_message'])
                                ->danger()
                                ->send();

                            throw new Halt;
                        }

                        if ($preview['ready_count'] === 0) {
                            Notification::make()
                                ->title(__('Nothing to create'))
                                ->body(__('No salary expenses are ready to be created with the current settings.'))
                                ->warning()
                                ->send();

                            throw new Halt;
                        }

                        if (! $preview['has_sufficient_balance']) {
                            Notification::make()
                                ->title(__('Insufficient account balance'))
                                ->body(__('The selected account only has :balance available, but this payroll needs :total.', [
                                    'balance' => number_format((float) $preview['bank_account_balance'], 2).' '.$preview['currency'],
                                    'total' => number_format((float) $preview['ready_total'], 2).' '.$preview['currency'],
                                ]))
                                ->danger()
                                ->send();

                            throw new Halt;
                        }

                        $result = $generator->generate($payDate, $skipExisting, bankAccountId: $bankAccountId);

                        $body = __('Created :c expense(s), skipped :s.', [
                            'c' => $result['created'],
                            's' => $result['skipped'],
                        ]);
                        if ($result['messages'] !== []) {
                            $body .= ' '.implode(' ', $result['messages']);
                        }

                        if ($result['created'] === 0) {
                            Notification::make()
                                ->title(__('Payroll generation finished'))
                                ->body($body)
                                ->warning()
                                ->send();

                            throw new Halt;
                        }

                        Notification::make()
                            ->title(__('Payroll generation finished'))
                            ->body($body)
                            ->success()
                            ->send();
                    } catch (ValidationException $exception) {
                        Notification::make()
                            ->title(__('Could not create payroll expenses'))
                            ->body(collect($exception->errors())->flatten()->first() ?? $exception->getMessage())
                            ->danger()
                            ->send();

                        throw new Halt;
                    }
                }),
            CreateAction::make(),
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function payrollBankAccountOptions(): array
    {
        $user = auth()->user();

        return BankAccount::query()
            ->when(
                $user?->isBranchRestricted(),
                fn (Builder $query) => $query->forAnyBranch($user->branchIds()),
            )
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function exportFilteredExpenses(): StreamedResponse
    {
        $query = $this->getFilteredTableQuery()
            ->with(['expenseType', 'bankAccount', 'branch', 'employee']);

        $total = (float) $query->clone()->sum('amount');
        $rows = $query->orderBy('date', 'desc')->get();

        return app(ExpenseExporter::class)->download(
            $rows,
            $total,
            $this->tableFilters ?? [],
        );
    }
}
