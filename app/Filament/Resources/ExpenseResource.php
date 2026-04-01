<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExpenseResource\Pages;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Setting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
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
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix($currency),
                Select::make('bank_account_id')
                    ->label('Pay From Account')
                    ->options(fn (Get $get) => BankAccount::query()
                        ->when($get('branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
                        ->orderByDesc('is_default')
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->default(fn () => BankAccount::getDefaultAccountForBranch(Branch::getDefaultBranch()?->id)?->id),
                Select::make('expense_type_id')
                    ->relationship('expenseType', 'name', fn ($query) => $query->where('is_active', true)->orderBy('name'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->placeholder('Select expense type')
                    ->createOptionForm([
                        \Filament\Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->default(true),
                    ]),
                TextInput::make('vendor')
                    ->maxLength(255)
                    ->placeholder('Vendor or payee'),
                Textarea::make('description')
                    ->columnSpanFull()
                    ->placeholder('Notes or description'),
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
                Tables\Columns\TextColumn::make('vendor')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(30)
                    ->placeholder('—'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                //
            ])
            ->actions([
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
