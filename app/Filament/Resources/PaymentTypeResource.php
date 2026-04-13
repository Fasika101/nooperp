<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentTypeResource\Pages;
use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\PaymentType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentTypeResource extends Resource
{
    protected static ?string $model = PaymentType::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Payment Types';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Cash, Card, Mobile Money'),
                Toggle::make('is_global')
                    ->label('Available at all branches')
                    ->helperText('When enabled, this payment type appears at every branch on POS and in payment pickers.')
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
                    ->live()
                    ->default(fn () => auth()->user()?->branch_id
                        ? [auth()->user()->branch_id]
                        : (Branch::getDefaultBranch()?->id ? [Branch::getDefaultBranch()->id] : []))
                    ->disabled(fn (): bool => auth()->user()?->isBranchRestricted() ?? false)
                    ->dehydrated(),
                Toggle::make('is_accounts_receivable')
                    ->label('On account (no bank deposit)')
                    ->helperText('Use for “sell now, collect later”. No money enters a bank account until you record a collection payment on the order. Requires a named customer at POS.')
                    ->live()
                    ->default(false),
                Select::make('bank_account_id')
                    ->label('Linked Account')
                    ->options(function (Get $get) {
                        $q = BankAccount::query();
                        if ($get('is_global') ?? false) {
                            // Any account can be chosen; user should pick one valid for their workflow.
                        } else {
                            $ids = array_values(array_filter(array_map('intval', $get('branches') ?? [])));
                            if (count($ids) > 1) {
                                $q->forAllBranches($ids);
                            } elseif (count($ids) === 1) {
                                $q->forBranch($ids[0]);
                            }
                        }

                        return $q->orderBy('name')->pluck('name', 'id');
                    })
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get) => ! $get('is_accounts_receivable'))
                    ->helperText('Sales paid with this method will increase this account. Not used for on-account sales.'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Only active payment types appear on POS'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('branch_scope')
                    ->label('Branches')
                    ->state(fn (PaymentType $record): string => $record->is_global
                        ? 'All branches'
                        : ($record->branches->pluck('name')->filter()->join(', ') ?: ($record->branch?->name ?? '—'))),
                Tables\Columns\TextColumn::make('bankAccount.name')
                    ->label('Linked Account')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_accounts_receivable')
                    ->label('On account')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
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
            'index' => Pages\ListPaymentTypes::route('/'),
            'create' => Pages\CreatePaymentType::route('/create'),
            'edit' => Pages\EditPaymentType::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['branches', 'branch', 'bankAccount']);
        $user = auth()->user();

        if ($user?->isBranchRestricted()) {
            $query->forBranch($user->branch_id);
        }

        return $query;
    }
}
