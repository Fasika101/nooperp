<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountsReceivablePage extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Accounts receivable';

    protected static ?string $title = 'Accounts receivable';

    protected static ?int $navigationSort = 3;

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
            ->heading('Open balances')
            ->description('Completed sales with an unpaid balance. Record payments from the order view.')
            ->query(function (): Builder {
                $query = Order::query()
                    ->with(['customer', 'branch'])
                    ->withBalanceDue()
                    ->orderByDesc('created_at');

                if (auth()->user()?->isBranchRestricted()) {
                    $query->whereIn('branch_id', auth()->user()->branchIds());
                }

                return $query;
            })
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->placeholder('—'),
                TextColumn::make('total_amount')
                    ->label('Order total')
                    ->money($currency)
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label('Paid')
                    ->money($currency)
                    ->sortable(),
                TextColumn::make('balance_due')
                    ->label('Balance due')
                    ->money($currency)
                    ->sortable()
                    ->color('danger'),
                TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid' => 'success',
                        'partial' => 'warning',
                        'unpaid' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('viewOrder')
                    ->label('View order')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
                    ->openUrlInNewTab(),
            ]);
    }
}
