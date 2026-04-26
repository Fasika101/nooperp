<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AffiliateResource\Pages;
use App\Filament\Resources\AffiliateResource\RelationManagers\AffiliateOrdersRelationManager;
use App\Filament\Resources\AffiliateResource\RelationManagers\AffiliatePayoutsRelationManager;
use App\Models\Affiliate;
use App\Models\ExpenseType;
use App\Models\Setting;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AffiliateResource extends Resource
{
    protected static ?string $model = Affiliate::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->label('Referral code')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Toggle::make('is_active')
                            ->default(true),
                    ])
                    ->columns(2),
                Section::make('Default commission (POS)')
                    ->description('Pre-filled on the POS affiliate modal. Staff can still change the rate for each sale.')
                    ->schema([
                        TextInput::make('default_commission_rate')
                            ->label('Default rate (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(0)
                            ->required(),
                        Select::make('default_commission_type')
                            ->label('Default mode')
                            ->options(self::commissionTypeOptions())
                            ->default(Affiliate::COMMISSION_DEDUCT_PERCENT)
                            ->required(),
                    ])
                    ->columns(2),
                Section::make('Notes')
                    ->schema([
                        Textarea::make('notes')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Section::make('Profile')
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('phone')
                            ->placeholder('—'),
                        TextEntry::make('email')
                            ->placeholder('—'),
                        TextEntry::make('code')
                            ->label('Referral code')
                            ->placeholder('—'),
                        TextEntry::make('is_active')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                            ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                    ])
                    ->columns(2),
                Section::make('Earnings (completed sales)')
                    ->schema([
                        TextEntry::make('total_commission')
                            ->label('Total earned (sum of commissions)')
                            ->money($currency)
                            ->state(function (Affiliate $record): float {
                                return (float) $record->orders()
                                    ->where('status', 'completed')
                                    ->whereNotNull('affiliate_id')
                                    ->sum('affiliate_commission_amount');
                            }),
                        TextEntry::make('order_count')
                            ->label('Sales count')
                            ->state(function (Affiliate $record): int {
                                return $record->orders()
                                    ->where('status', 'completed')
                                    ->whereNotNull('affiliate_id')
                                    ->count();
                            }),
                        TextEntry::make('total_paid_payouts')
                            ->label('Total paid (payout expenses)')
                            ->money($currency)
                            ->state(function (Affiliate $record): float {
                                $typeId = ExpenseType::affiliatePayoutTypeId();
                                if (! $typeId) {
                                    return 0.0;
                                }

                                return (float) $record->commissionPayouts()->sum('amount');
                            }),
                        TextEntry::make('commission_balance')
                            ->label('Balance (earned minus paid)')
                            ->money($currency)
                            ->state(function (Affiliate $record): float {
                                $earned = (float) $record->orders()
                                    ->where('status', 'completed')
                                    ->sum('affiliate_commission_amount');
                                $typeId = ExpenseType::affiliatePayoutTypeId();
                                $paid = $typeId
                                    ? (float) $record->commissionPayouts()->sum('amount')
                                    : 0.0;

                                return round($earned - $paid, 2);
                            })
                            ->color(function (Affiliate $record): string {
                                $earned = (float) $record->orders()
                                    ->where('status', 'completed')
                                    ->sum('affiliate_commission_amount');
                                $typeId = ExpenseType::affiliatePayoutTypeId();
                                $paid = $typeId
                                    ? (float) $record->commissionPayouts()->sum('amount')
                                    : 0.0;
                                $bal = $earned - $paid;

                                return $bal > 0.01 ? 'warning' : ($bal < -0.01 ? 'danger' : 'success');
                            }),
                    ])
                    ->columns(2),
                Section::make('Default POS settings')
                    ->schema([
                        TextEntry::make('default_commission_rate')
                            ->label('Default rate (%)')
                            ->suffix('%'),
                        TextEntry::make('default_commission_type')
                            ->label('Default mode')
                            ->formatStateUsing(fn (?string $state): string => self::commissionTypeOptions()[$state] ?? $state ?? '—'),
                    ])
                    ->columns(2)
                    ->collapsed(),
                Section::make('Notes')
                    ->schema([
                        TextEntry::make('notes')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
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
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('default_commission_type')
                    ->label('Mode')
                    ->formatStateUsing(fn (?string $state): string => self::commissionTypeOptions()[$state] ?? '—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('default_commission_rate')
                    ->label('Default %')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('orders_sum_affiliate_commission_amount')
                    ->label('Total earned')
                    ->money($currency)
                    ->sortable()
                    ->placeholder('0'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
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
            AffiliateOrdersRelationManager::class,
            AffiliatePayoutsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliates::route('/'),
            'create' => Pages\CreateAffiliate::route('/create'),
            'view' => Pages\ViewAffiliate::route('/{record}'),
            'edit' => Pages\EditAffiliate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withSum([
                'orders' => fn (Builder $q) => $q->where('status', 'completed'),
            ], 'affiliate_commission_amount');
    }

    /**
     * @return array<string, string>
     */
    public static function commissionTypeOptions(): array
    {
        return [
            Affiliate::COMMISSION_DEDUCT_PERCENT => 'Deduct % from sale (customer pays pre-affiliate total; you track commission)',
            Affiliate::COMMISSION_ADD_PERCENT => 'Add % to sale (customer pays base + commission)',
        ];
    }
}
