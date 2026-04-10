<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeResource\Schemas;

use App\Models\Branch;
use App\Models\Employee;
use App\Services\PayrollTaxCalculator;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Text as SchemaText;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Spatie\Permission\Models\Role;

final class EmployeeFormSchema
{
    /**
     * @return list<Step>
     */
    public static function wizardSteps(string $currency): array
    {
        return [
            Step::make('Personal')
                ->description('Basic profile and identification')
                ->columns(2)
                ->schema([
                    TextInput::make('employee_code')
                        ->label('Employee code')
                        ->maxLength(50)
                        ->unique(ignoreRecord: true)
                        ->helperText('Leave blank to auto-generate (e.g. EMP-000001).')
                        ->columnSpanFull(),
                    TextInput::make('full_name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('email')
                        ->email()
                        ->maxLength(255),
                    TextInput::make('phone')
                        ->tel()
                        ->maxLength(255),
                    DatePicker::make('date_of_birth'),
                    Select::make('gender')
                        ->options([
                            'male' => 'Male',
                            'female' => 'Female',
                            'other' => 'Other',
                            'prefer_not' => 'Prefer not to say',
                        ])
                        ->native(false),
                    Textarea::make('address')
                        ->rows(2)
                        ->columnSpanFull(),
                    TextInput::make('national_id')
                        ->label('National ID / passport')
                        ->maxLength(100),
                    Textarea::make('notes')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
            Step::make('Employment')
                ->description('Role, status, and dates')
                ->columns(2)
                ->schema([
                    Select::make('department_id')
                        ->relationship('department', 'name')
                        ->searchable()
                        ->preload(),
                    Select::make('job_position_id')
                        ->relationship('jobPosition', 'title')
                        ->searchable()
                        ->preload(),
                    Select::make('manager_id')
                        ->label('Reports to')
                        ->relationship('manager', 'full_name')
                        ->searchable()
                        ->preload(),
                    Select::make('branch_id')
                        ->label('Work branch')
                        ->relationship('branch', 'name')
                        ->searchable()
                        ->preload(),
                    Select::make('employment_type')
                        ->options(Employee::employmentTypeOptions())
                        ->required()
                        ->native(false),
                    Select::make('employment_status')
                        ->options(Employee::employmentStatusOptions())
                        ->required()
                        ->native(false),
                    DatePicker::make('hire_date')
                        ->label('Start / hire date')
                        ->required()
                        ->default(now()),
                    DatePicker::make('probation_end_date'),
                    DatePicker::make('termination_date'),
                    Textarea::make('termination_notes')
                        ->rows(2)
                        ->columnSpanFull(),
                ]),
            Step::make('Contact')
                ->description('Emergency contacts')
                ->columns(2)
                ->schema([
                    TextInput::make('emergency_contact_name')
                        ->maxLength(255),
                    TextInput::make('emergency_contact_phone')
                        ->tel()
                        ->maxLength(255),
                ]),
            Step::make('Banking')
                ->description('Bank details and payroll identifiers')
                ->columns(2)
                ->schema([
                    TextInput::make('bank_name')->maxLength(255),
                    TextInput::make('bank_account_no')->maxLength(255),
                    TextInput::make('payroll_tax_id')
                        ->label('Tax / payroll ID')
                        ->maxLength(100),
                    TextInput::make('salary_currency')
                        ->default($currency)
                        ->maxLength(3)
                        ->placeholder('ETB'),
                ]),
            Step::make('Hours & rates')
                ->description('Salary and estimated payroll tax from Settings brackets')
                ->columns(2)
                ->schema([
                    TextInput::make('hours_per_day')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(24)
                        ->step(0.25),
                    TextInput::make('days_per_week')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(7)
                        ->step(0.25),
                    TextInput::make('hourly_rate')
                        ->label('Hourly rate')
                        ->numeric()
                        ->prefix($currency)
                        ->minValue(0),
                    TextInput::make('base_salary')
                        ->numeric()
                        ->prefix($currency)
                        ->minValue(0)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Set $set): void {
                            $r = PayrollTaxCalculator::calculate((float) ($state ?? 0));
                            $set('payroll_tax_amount', $r['tax']);
                            $set('net_salary_after_tax', $r['net']);
                        }),
                    DatePicker::make('salary_effective_date')
                        ->label('Salary effective from'),
                    Select::make('pay_frequency')
                        ->options(Employee::payFrequencyOptions())
                        ->required()
                        ->native(false),
                    TextInput::make('payroll_tax_amount')
                        ->label('Estimated payroll tax')
                        ->numeric()
                        ->prefix($currency)
                        ->disabled()
                        ->dehydrated()
                        ->helperText(fn (Get $get): string => self::taxPreviewLine($get('base_salary'))),
                    TextInput::make('net_salary_after_tax')
                        ->label('Estimated net after tax')
                        ->numeric()
                        ->prefix($currency)
                        ->disabled()
                        ->dehydrated(),
                ]),
            Step::make('Documents')
                ->description('Upload files after the employee is created')
                ->schema([
                    SchemaText::make(
                        'You can add contracts, IDs, and other files from the employee profile using the Documents tab after saving.',
                    )
                        ->columnSpanFull(),
                ]),
            Step::make('Panel login')
                ->description('Optional Filament access for this hire')
                ->schema([
                    Toggle::make('create_panel_user')
                        ->label('Create panel user account')
                        ->default(false)
                        ->live(),
                    TextInput::make('new_user_password')
                        ->password()
                        ->revealable()
                        ->helperText('Leave empty to auto-generate; the password is shown once in a notification.')
                        ->visible(fn (Get $get): bool => (bool) $get('create_panel_user')),
                    Select::make('new_user_branch_id')
                        ->label('User default branch')
                        ->options(fn () => Branch::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->visible(fn (Get $get): bool => (bool) $get('create_panel_user')),
                    Select::make('new_user_role_ids')
                        ->label('Roles')
                        ->multiple()
                        ->options(fn () => Role::query()->orderBy('name')->pluck('name', 'id'))
                        ->searchable()
                        ->preload()
                        ->required(fn (Get $get): bool => (bool) $get('create_panel_user'))
                        ->visible(fn (Get $get): bool => (bool) $get('create_panel_user')),
                ]),
        ];
    }

    private static function taxPreviewLine(mixed $baseSalary): string
    {
        $gross = (float) ($baseSalary ?? 0);
        if ($gross <= 0) {
            return 'From Salary tax brackets (Settings). Enter base salary on blur to preview bands.';
        }

        return 'From Salary tax brackets (Settings). '.PayrollTaxCalculator::summaryLine($gross);
    }
}
