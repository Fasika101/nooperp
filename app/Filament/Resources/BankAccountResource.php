<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BankAccountResource\Pages;
use App\Filament\Resources\BankAccountResource\RelationManagers\TransactionsRelationManager;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\Setting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BankAccountResource extends Resource
{
    protected static ?string $model = BankAccount::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Bank Accounts';

    protected static ?string $modelLabel = 'Bank Account';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Toggle::make('is_global')
                    ->label('Available at all branches')
                    ->helperText('When enabled, this account can be used for deposits, sales, and expenses at any branch.')
                    ->live()
                    ->default(false)
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if ($state) {
                            $set('branches', []);
                        }
                    }),
                Select::make('branches')
                    ->label('Branches')
                    ->multiple()
                    ->relationship(
                        'branches',
                        'name',
                        fn ($query) => $query->where('is_active', true)->orderByDesc('is_default')->orderBy('name'),
                    )
                    ->required(fn (Get $get): bool => ! ($get('is_global') ?? false))
                    ->visible(fn (Get $get): bool => ! ($get('is_global') ?? false))
                    ->searchable()
                    ->preload()
                    ->default(fn () => auth()->user()?->branch_id
                        ? [auth()->user()->primaryBranchId() ?? auth()->user()->branch_id]
                        : (Branch::getDefaultBranch()?->id ? [Branch::getDefaultBranch()->id] : []))
                    ->disabled(fn (): bool => auth()->user()?->isBranchRestricted() ?? false)
                    ->dehydrated(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Main Business Account'),
                TextInput::make('bank_name')
                    ->maxLength(255)
                    ->placeholder('Bank name'),
                TextInput::make('account_number')
                    ->maxLength(255)
                    ->placeholder('Account number'),
                TextInput::make('currency')
                    ->maxLength(3)
                    ->default(fn () => Setting::getDefaultCurrency())
                    ->placeholder('ETB'),
                TextInput::make('opening_balance')
                    ->label('Opening Balance (Capital)')
                    ->numeric()
                    ->default(0)
                    ->prefix($currency)
                    ->helperText('Initial capital when you opened this account'),
                TextInput::make('current_balance')
                    ->label('Current Balance')
                    ->numeric()
                    ->default(0)
                    ->prefix($currency)
                    ->helperText('Running balance for this account. Adjust it only if you need to correct the ledger.'),
                Toggle::make('is_default')
                    ->label('Default account')
                    ->helperText('Use as primary account for display'),
                Textarea::make('notes')
                    ->columnSpanFull()
                    ->placeholder('Optional notes'),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch_scope')
                    ->label('Branches')
                    ->state(fn (BankAccount $record): string => $record->is_global
                        ? 'All branches'
                        : ($record->branches->pluck('name')->filter()->join(', ') ?: ($record->branch?->name ?? '—'))),
                Tables\Columns\TextColumn::make('bank_name')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('opening_balance')
                    ->label('Capital')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_balance')
                    ->label('Balance')
                    ->money($currency)
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
            ])
            ->defaultSort('name')
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
        return [
            TransactionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBankAccounts::route('/'),
            'create' => Pages\CreateBankAccount::route('/create'),
            'edit' => Pages\EditBankAccount::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['branches', 'branch']);
        $user = auth()->user();

        if ($user?->isBranchRestricted()) {
            $query->forAnyBranch($user->branchIds());
        }

        return $query;
    }
}
