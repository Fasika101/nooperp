<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmployeeResource\Pages;
use App\Filament\Resources\EmployeeResource\RelationManagers\AttendanceEntriesRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\EmployeeDocumentsRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\EmployeeExpensesRelationManager;
use App\Filament\Resources\EmployeeResource\RelationManagers\LeaveRequestsRelationManager;
use App\Models\Employee;
use App\Models\Setting;
use App\Services\PayrollTaxCalculator;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'HR';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Employees';

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Section::make('Profile & contact')
                    ->schema([
                        TextInput::make('employee_code')
                            ->label('Employee code')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->helperText('Leave blank to auto-generate (e.g. EMP-000001).'),
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
                    ])
                    ->columns(2),
                Section::make('Emergency contact')
                    ->schema([
                        TextInput::make('emergency_contact_name')
                            ->maxLength(255),
                        TextInput::make('emergency_contact_phone')
                            ->tel()
                            ->maxLength(255),
                    ])
                    ->columns(2)
                    ->collapsed(),
                Section::make('Employment')
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
                    ])
                    ->columns(2),
                Section::make('Banking & payroll IDs')
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
                    ])
                    ->columns(2),
                Section::make('Compensation & payroll')
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
                            ->helperText(fn (Get $get): string => self::payrollTaxPreviewLine($get('base_salary'))),
                        TextInput::make('net_salary_after_tax')
                            ->label('Estimated net after tax')
                            ->numeric()
                            ->prefix($currency)
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns(2),
                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')->rows(4)->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $defaultCurrency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Section::make('Profile & contact')
                    ->schema([
                        TextEntry::make('employee_code')
                            ->label('Employee code')
                            ->placeholder('—'),
                        TextEntry::make('full_name')
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('email')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('phone')
                            ->placeholder('—')
                            ->copyable(),
                        TextEntry::make('date_of_birth')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('gender')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                                'prefer_not' => 'Prefer not to say',
                                default => $state ?? '—',
                            })
                            ->placeholder('—'),
                        TextEntry::make('address')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('national_id')
                            ->label('National ID / passport')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Emergency contact')
                    ->schema([
                        TextEntry::make('emergency_contact_name')
                            ->placeholder('—'),
                        TextEntry::make('emergency_contact_phone')
                            ->placeholder('—')
                            ->copyable(),
                    ])
                    ->columns(2)
                    ->collapsed(),
                Section::make('Employment')
                    ->schema([
                        TextEntry::make('department.name')
                            ->label('Department')
                            ->placeholder('—'),
                        TextEntry::make('jobPosition.title')
                            ->label('Position')
                            ->placeholder('—'),
                        TextEntry::make('manager.full_name')
                            ->label('Reports to')
                            ->placeholder('—')
                            ->url(function (TextEntry $component): ?string {
                                $record = $component->getRecord();
                                if (! $record instanceof Employee || ! $record->manager_id) {
                                    return null;
                                }

                                return static::getUrl('view', ['record' => $record->manager_id]);
                            }),
                        TextEntry::make('branch.name')
                            ->label('Work branch')
                            ->placeholder('—'),
                        TextEntry::make('employment_type')
                            ->label('Employment type')
                            ->formatStateUsing(fn (?string $state): string => $state
                                ? (Employee::employmentTypeOptions()[$state] ?? $state)
                                : '—'),
                        TextEntry::make('employment_status')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state
                                ? (Employee::employmentStatusOptions()[$state] ?? $state)
                                : '—'),
                        TextEntry::make('hire_date')
                            ->date(),
                        TextEntry::make('probation_end_date')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('termination_date')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('termination_notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
                Section::make('Compensation & payroll')
                    ->schema([
                        TextEntry::make('hours_per_day')
                            ->label('Hours / day')
                            ->placeholder('—'),
                        TextEntry::make('days_per_week')
                            ->label('Days / week')
                            ->placeholder('—'),
                        TextEntry::make('hourly_rate')
                            ->label('Hourly rate')
                            ->formatStateUsing(function (TextEntry $component, $state) use ($defaultCurrency): string {
                                if ($state === null || $state === '') {
                                    return '—';
                                }
                                $record = $component->getRecord();
                                $currency = $record instanceof Employee
                                    ? ($record->salary_currency ?: $defaultCurrency)
                                    : $defaultCurrency;

                                return Number::currency((float) $state, $currency);
                            }),
                        TextEntry::make('base_salary')
                            ->label('Base salary')
                            ->formatStateUsing(function (TextEntry $component, $state) use ($defaultCurrency): string {
                                if ($state === null || $state === '') {
                                    return '—';
                                }
                                $record = $component->getRecord();
                                $currency = $record instanceof Employee
                                    ? ($record->salary_currency ?: $defaultCurrency)
                                    : $defaultCurrency;

                                return Number::currency((float) $state, $currency);
                            }),
                        TextEntry::make('payroll_tax_amount')
                            ->label('Payroll tax (estimate)')
                            ->formatStateUsing(function (TextEntry $component, $state) use ($defaultCurrency): string {
                                if ($state === null || $state === '') {
                                    return '—';
                                }
                                $record = $component->getRecord();
                                $currency = $record instanceof Employee
                                    ? ($record->salary_currency ?: $defaultCurrency)
                                    : $defaultCurrency;

                                return Number::currency((float) $state, $currency);
                            }),
                        TextEntry::make('net_salary_after_tax')
                            ->label('Net after tax (estimate)')
                            ->formatStateUsing(function (TextEntry $component, $state) use ($defaultCurrency): string {
                                if ($state === null || $state === '') {
                                    return '—';
                                }
                                $record = $component->getRecord();
                                $currency = $record instanceof Employee
                                    ? ($record->salary_currency ?: $defaultCurrency)
                                    : $defaultCurrency;

                                return Number::currency((float) $state, $currency);
                            }),
                        TextEntry::make('salary_effective_date')
                            ->label('Salary effective from')
                            ->date()
                            ->placeholder('—'),
                        TextEntry::make('pay_frequency')
                            ->formatStateUsing(fn (?string $state): string => $state
                                ? (Employee::payFrequencyOptions()[$state] ?? $state)
                                : '—'),
                        TextEntry::make('salary_currency')
                            ->placeholder('—'),
                    ])
                    ->columns(2),
                Section::make('Banking')
                    ->schema([
                        TextEntry::make('bank_name')
                            ->placeholder('—'),
                        TextEntry::make('bank_account_no')
                            ->placeholder('—'),
                        TextEntry::make('payroll_tax_id')
                            ->label('Tax / payroll ID')
                            ->placeholder('—'),
                    ])
                    ->columns(2)
                    ->collapsed(),
                Section::make('Panel login')
                    ->schema([
                        TextEntry::make('user.email')
                            ->label('Linked user email')
                            ->placeholder('—')
                            ->copyable(),
                    ])
                    ->columns(1)
                    ->collapsed(),
                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make('Record')
                    ->schema([
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('—'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->placeholder('—'),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['department', 'jobPosition', 'user']))
            ->columns([
                Tables\Columns\TextColumn::make('employee_code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('full_name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('jobPosition.title')
                    ->label('Position')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('hire_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('employment_status')
                    ->badge(),
                Tables\Columns\TextColumn::make('base_salary')
                    ->money($currency)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Panel login')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('hire_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name'),
                Tables\Filters\SelectFilter::make('employment_status')
                    ->options(Employee::employmentStatusOptions()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            EmployeeDocumentsRelationManager::class,
            EmployeeExpensesRelationManager::class,
            LeaveRequestsRelationManager::class,
            AttendanceEntriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'view' => Pages\ViewEmployee::route('/{record}'),
            'edit' => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }

    private static function payrollTaxPreviewLine(mixed $baseSalary): string
    {
        $gross = (float) ($baseSalary ?? 0);
        if ($gross <= 0) {
            return 'From Salary tax brackets (Settings). Enter base salary on blur to preview bands.';
        }

        return 'From Salary tax brackets (Settings). '.PayrollTaxCalculator::summaryLine($gross);
    }
}
