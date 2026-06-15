<?php

namespace App\Filament\Projects\Resources\ProjectResource\RelationManagers;

use App\Models\BankAccount;
use App\Models\Expense;
use App\Models\ExpenseType;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectExpensesRelationManager extends RelationManager
{
    protected static string $relationship = 'expenses';

    protected static ?string $title = 'Expenses';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('date')
                    ->required()
                    ->default(now()),

                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix('ETB'),

                Select::make('expense_type_id')
                    ->label('Expense type')
                    ->options(fn () => ExpenseType::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('bank_account_id')
                    ->label('Pay from account')
                    ->options(fn () => BankAccount::orderBy('name')->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('vendor')
                    ->maxLength(255)
                    ->placeholder('Vendor / payee'),

                Textarea::make('description')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')->date()->sortable(),
                Tables\Columns\TextColumn::make('expenseType.name')
                    ->label('Type')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('amount')
                    ->prefix('ETB ')
                    ->numeric(decimalPlaces: 2)
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('Total')->prefix('ETB ')),
                Tables\Columns\TextColumn::make('vendor')->placeholder('—'),
                Tables\Columns\TextColumn::make('description')->limit(60)->placeholder('—'),
            ])
            ->defaultSort('date', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('Add Expense')
                    ->using(function (array $data): Expense {
                        $data['project_id'] = $this->getOwnerRecord()->id;

                        return Expense::create($data);
                    }),
            ]);
    }
}
