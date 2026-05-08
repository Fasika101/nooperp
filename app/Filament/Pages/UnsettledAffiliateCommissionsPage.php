<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\BankAccount;
use App\Models\Order;
use App\Models\Setting;
use App\Services\AffiliateCommissionSettlementService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class UnsettledAffiliateCommissionsPage extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Unsettled affiliate commissions';

    protected static ?string $title = 'Unsettled affiliate commissions';

    protected static ?int $navigationSort = 4;

    public function mount(): void
    {
        $this->bootedInteractsWithTable();
        $this->mountInteractsWithTable();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->heading('Orders with unpaid affiliate commission')
            ->description('Select rows that share the same affiliate and branch, then use Settle selected to create one payout expense, record the bank withdrawal, and mark each order’s remaining commission as paid for this batch.')
            ->query(function (): Builder {
                $query = Order::query()
                    ->with(['customer', 'affiliate', 'branch'])
                    ->where('status', 'completed')
                    ->whereNotNull('affiliate_id')
                    ->where('affiliate_commission_amount', '>', 0)
                    ->whereRaw(
                        'affiliate_commission_amount > (SELECT COALESCE(SUM(amount), 0) FROM affiliate_commission_settlements WHERE affiliate_commission_settlements.order_id = orders.id)'
                    )
                    ->withSum('affiliateCommissionSettlements as commission_settled_sum', 'amount');

                if (auth()->user()?->isBranchRestricted()) {
                    $query->whereIn('branch_id', auth()->user()->branchIds());
                }

                return $query->orderByDesc('created_at');
            })
            ->columns([
                TextColumn::make('id')
                    ->label('Order #')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('affiliate.name')
                    ->label('Affiliate')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—'),
                TextColumn::make('affiliate_commission_amount')
                    ->label('Commission')
                    ->money($currency)
                    ->sortable(),
                TextColumn::make('commission_settled_sum')
                    ->label('Settled so far')
                    ->money($currency)
                    ->placeholder('0')
                    ->sortable(),
                TextColumn::make('commission_remaining')
                    ->label('Remaining')
                    ->money($currency)
                    ->state(function (Order $record): float {
                        $settled = (float) ($record->commission_settled_sum ?? 0);

                        return round(max(0, (float) $record->affiliate_commission_amount - $settled), 2);
                    })
                    ->color('warning'),
                TextColumn::make('settlement_status')
                    ->label('Status')
                    ->badge()
                    ->state(function (Order $record): string {
                        $settled = (float) ($record->commission_settled_sum ?? 0);
                        $comm = (float) $record->affiliate_commission_amount;
                        if ($comm <= 0) {
                            return '—';
                        }
                        if ($settled <= 0.001) {
                            return 'Unpaid';
                        }
                        if ($settled + 0.01 >= $comm) {
                            return 'Paid';
                        }

                        return 'Partial';
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'Paid' => 'success',
                        'Partial' => 'warning',
                        'Unpaid' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('affiliate_id')
                    ->label('Affiliate')
                    ->relationship('affiliate', 'name', fn ($query) => $query->orderBy('name'))
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->bulkActions([
                BulkAction::make('settleSelected')
                    ->label('Settle selected')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->schema(function (BulkAction $action): array {
                        $records = $action->getSelectedRecords();
                        if ($records->isEmpty()) {
                            return [];
                        }
                        $branchId = (int) $records->first()->branch_id;

                        return [
                            DatePicker::make('date')
                                ->label('Payout date')
                                ->required()
                                ->default(now())
                                ->native(false),
                            Select::make('bank_account_id')
                                ->label('Pay from account')
                                ->options(
                                    BankAccount::query()
                                        ->forBranch($branchId)
                                        ->orderByDesc('is_default')
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all()
                                )
                                ->required()
                                ->searchable()
                                ->preload()
                                ->helperText('Withdrawal uses this account for the total remaining commission on the selected orders.'),
                            Textarea::make('description')
                                ->label('Notes (optional)')
                                ->placeholder('If empty, affected order IDs are listed on the expense.'),
                        ];
                    })
                    ->action(function (array $data, BulkAction $action): void {
                        try {
                            $expense = app(AffiliateCommissionSettlementService::class)->settle(
                                $action->getSelectedRecords(),
                                $data,
                            );
                            Notification::make()
                                ->success()
                                ->title('Commission settled')
                                ->body('Expense #'.$expense->id.' — '.number_format((float) $expense->amount, 2).' '.Setting::getDefaultCurrency())
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Could not settle')
                                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                                ->send();
                        }
                    })
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
