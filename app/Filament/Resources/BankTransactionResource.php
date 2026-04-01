<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankTransactionResource\Pages;
use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\Setting;
use Filament\Actions\DeleteAction;
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

class BankTransactionResource extends Resource
{
    protected static ?string $model = BankTransaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Bank Transactions';

    protected static ?string $modelLabel = 'Bank Transaction';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Select::make('bank_account_id')
                    ->relationship('bankAccount', 'name', fn ($query) => $query
                        ->when(auth()->user()?->isBranchRestricted(), fn ($query) => $query->where('branch_id', auth()->user()?->branch_id))
                        ->orderBy('name'))
                    ->required()
                    ->searchable()
                    ->preload(),
                DatePicker::make('date')
                    ->required()
                    ->default(now()),
                Select::make('type')
                    ->options([
                        BankTransaction::TYPE_DEPOSIT => 'Deposit',
                        BankTransaction::TYPE_WITHDRAWAL => 'Withdrawal',
                        BankTransaction::TYPE_TRANSFER => 'Transfer',
                    ])
                    ->live()
                    ->required(),
                Select::make('destination_bank_account_id')
                    ->label('Destination Account')
                    ->options(fn () => BankAccount::query()
                        ->when(auth()->user()?->isBranchRestricted(), fn ($query) => $query->where('branch_id', auth()->user()?->branch_id))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => $get('type') === BankTransaction::TYPE_TRANSFER)
                    ->different('bank_account_id')
                    ->required(fn (Get $get): bool => $get('type') === BankTransaction::TYPE_TRANSFER),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix($currency),
                Textarea::make('description')
                    ->columnSpanFull()
                    ->placeholder('Optional description or reference'),
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
                Tables\Columns\TextColumn::make('bankAccount.name')
                    ->label('Account')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state, BankTransaction $record): string => $record->getDisplayTypeLabel())
                    ->color(fn (string $state, BankTransaction $record): string => $record->getDisplayTypeColor()),
                Tables\Columns\TextColumn::make('amount')
                    ->money($currency)
                    ->sortable()
                    ->color(fn (BankTransaction $record): string => $record->getBalanceImpact() >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('counterparty')
                    ->label('Counterparty')
                    ->state(fn (BankTransaction $record): ?string => $record->getCounterpartyAccountName())
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('reference_type')
                    ->label('Linked to')
                    ->formatStateUsing(fn ($state, BankTransaction $record) => $record->reference
                        ? (class_basename($record->reference_type) . ' #' . $record->reference_id)
                        : '—')
                    ->placeholder('—'),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('bank_account_id')
                    ->relationship('bankAccount', 'name')
                    ->label('Account'),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        BankTransaction::TYPE_DEPOSIT => 'Deposit',
                        BankTransaction::TYPE_WITHDRAWAL => 'Withdrawal',
                    ]),
                Tables\Filters\Filter::make('transfers')
                    ->label('Transfers')
                    ->query(fn ($query) => $query->whereNotNull('linked_transaction_id')),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (BankTransaction $record): bool => ! $record->isTransferEntry()),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankTransactions::route('/'),
            'create' => Pages\CreateBankTransaction::route('/create'),
            'edit' => Pages\EditBankTransaction::route('/{record}/edit'),
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
