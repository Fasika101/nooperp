<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\Setting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExpenseResource extends Resource
{
    protected static ?string $model = Expense::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Expenses';

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Select::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name', fn ($query) => $query->where('is_active', true)->orderByDesc('is_default')->orderBy('name'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->default(fn () => auth()->user()?->branch_id ?: Branch::getDefaultBranch()?->id)
                    ->disabled(fn () => auth()->user()?->isBranchRestricted() ?? false)
                    ->dehydrated(),
                DatePicker::make('date')
                    ->required()
                    ->default(now()),
                Select::make('expense_type_id')
                    ->relationship('expenseType', 'name', fn (Builder $query) => $query->where('is_active', true)->orderBy('name'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->placeholder('Select expense type')
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->default(true),
                    ]),
                Select::make('employee_id')
                    ->label('Employee')
                    ->relationship(
                        'employee',
                        'full_name',
                        fn (Builder $query) => $query
                            ->where('employment_status', '!=', Employee::STATUS_TERMINATED)
                            ->orderBy('full_name'),
                    )
                    ->searchable()
                    ->preload()
                    ->visible(function (Get $get): bool {
                        $sid = ExpenseType::salariesTypeId();

                        return $sid !== null && (int) $get('expense_type_id') === $sid;
                    })
                    ->required(function (Get $get): bool {
                        $sid = ExpenseType::salariesTypeId();

                        return $sid !== null && (int) $get('expense_type_id') === $sid;
                    })
                    ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                        $sid = ExpenseType::salariesTypeId();
                        if (! $state || $sid === null || (int) $get('expense_type_id') !== $sid) {
                            return;
                        }
                        $employee = Employee::query()->find($state);
                        if (! $employee) {
                            return;
                        }
                        $set('amount', $employee->base_salary);
                        $set('vendor', $employee->full_name);
                        if ($employee->branch_id) {
                            $set('branch_id', $employee->branch_id);
                        }
                        $bankId = BankAccount::getDefaultAccountForBranch($employee->branch_id)?->id;
                        if ($bankId) {
                            $set('bank_account_id', $bankId);
                        }
                    }),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix($currency),
                Select::make('bank_account_id')
                    ->label('Pay From Account')
                    ->options(fn (Get $get) => BankAccount::query()
                        ->when($get('branch_id'), fn (Builder $query, $branchId) => $query->where('branch_id', $branchId))
                        ->orderByDesc('is_default')
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn () => BankAccount::getDefaultAccountForBranch(Branch::getDefaultBranch()?->id)?->id),
                TextInput::make('vendor')
                    ->maxLength(255)
                    ->placeholder('Vendor or payee'),
                Textarea::make('description')
                    ->columnSpanFull()
                    ->placeholder('Notes or description'),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Section::make('Expense')
                    ->schema([
                        TextEntry::make('date')
                            ->date(),
                        TextEntry::make('amount')
                            ->money($currency),
                        TextEntry::make('branch.name')
                            ->label('Branch')
                            ->placeholder('—'),
                        TextEntry::make('bankAccount.name')
                            ->label('Pay from account')
                            ->placeholder('—'),
                        TextEntry::make('expenseType.name')
                            ->label('Type')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('employee.full_name')
                            ->label('Employee')
                            ->placeholder('—')
                            ->url(function (TextEntry $component): ?string {
                                $record = $component->getRecord();
                                if (! $record instanceof Expense || ! $record->employee_id) {
                                    return null;
                                }

                                return EmployeeResource::getUrl('view', ['record' => $record->employee_id]);
                            }),
                        TextEntry::make('vendor')
                            ->placeholder('—'),
                        TextEntry::make('description')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
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
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('bankAccount.name')
                    ->label('Account')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('expenseType.name')
                    ->label('Type')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(30)
                    ->placeholder('—'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'full_name', fn (Builder $query) => $query->orderBy('full_name'))
                    ->searchable()
                    ->preload(),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExpenses::route('/'),
            'create' => Pages\CreateExpense::route('/create'),
            'view' => Pages\ViewExpense::route('/{record}'),
            'edit' => Pages\EditExpense::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user?->isBranchRestricted()) {
            $query->where('branch_id', $user->branch_id);
        }

        return $query;
    }
}
