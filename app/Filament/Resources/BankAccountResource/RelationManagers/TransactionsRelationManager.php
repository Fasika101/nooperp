<?php

namespace App\Filament\Resources\BankAccountResource\RelationManagers;

use App\Models\BankTransaction;
use App\Models\Branch;
use App\Models\Setting;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transactions';

    public function form(Schema $schema): Schema
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
                    ->default(fn () => auth()->user()?->branch_id ?: Branch::getDefaultBranch()?->id)
                    ->disabled(fn (): bool => auth()->user()?->isBranchRestricted() ?? false)
                    ->dehydrated(),
                DatePicker::make('date')
                    ->required()
                    ->default(now()),
                Select::make('type')
                    ->options([
                        BankTransaction::TYPE_DEPOSIT => 'Deposit',
                        BankTransaction::TYPE_WITHDRAWAL => 'Withdrawal',
                        BankTransaction::TYPE_TRANSFER => 'Transfer',
                    ])
                    ->required(),
                TextInput::make('amount')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->prefix($currency),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state, BankTransaction $record): string => $record->getDisplayTypeLabel())
                    ->color(fn (string $state, BankTransaction $record): string => $record->getDisplayTypeColor()),
                Tables\Columns\TextColumn::make('amount')
                    ->money($currency)
                    ->color(fn (BankTransaction $record): string => $record->getBalanceImpact() >= 0 ? 'success' : 'danger'),
                Tables\Columns\TextColumn::make('counterparty')
                    ->label('Counterparty')
                    ->state(fn (BankTransaction $record): ?string => $record->getCounterpartyAccountName())
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('description')
                    ->limit(40)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('reference_type')
                    ->label('Linked')
                    ->formatStateUsing(fn ($state, BankTransaction $record) => $record->reference
                        ? class_basename($record->reference_type).' #'.$record->reference_id
                        : '—')
                    ->placeholder('—'),
            ])
            ->defaultSort('date', 'desc')
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn (BankTransaction $record): bool => ! $record->isTransferEntry()),
                DeleteAction::make(),
            ]);
    }
}
