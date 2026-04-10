<?php

namespace App\Filament\Resources\ExpenseResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Services\PayrollExpenseGenerator;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListExpenses extends ListRecords
{
    protected static string $resource = ExpenseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generatePayroll')
                ->label('Generate payroll expenses')
                ->icon('heroicon-o-rectangle-stack')
                ->color('gray')
                ->modalHeading('Generate salary expenses')
                ->modalDescription('Creates one “Salaries” expense per eligible employee (active, probation, or on leave) using their base salary for the chosen pay date’s month. Skips employees without branch or default bank account.')
                ->form([
                    DatePicker::make('pay_date')
                        ->label('Pay date')
                        ->required()
                        ->default(now()->startOfMonth())
                        ->native(false),
                    Toggle::make('skip_existing')
                        ->label('Skip if this employee already has a salary expense in that month')
                        ->default(true),
                ])
                ->action(function (array $data): void {
                    $generator = app(PayrollExpenseGenerator::class);
                    $result = $generator->generate(
                        Carbon::parse($data['pay_date']),
                        (bool) ($data['skip_existing'] ?? true),
                    );

                    $body = __('Created :c expense(s), skipped :s.', [
                        'c' => $result['created'],
                        's' => $result['skipped'],
                    ]);
                    if ($result['messages'] !== []) {
                        $body .= ' '.implode(' ', $result['messages']);
                    }

                    $n = Notification::make()
                        ->title(__('Payroll generation finished'))
                        ->body($body);
                    if ($result['created'] === 0) {
                        $n->warning();
                    } else {
                        $n->success();
                    }
                    $n->send();
                }),
            CreateAction::make(),
        ];
    }
}
