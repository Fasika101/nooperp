<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\PaymentType;
use App\Models\Setting;
use App\Services\AccountsReceivableService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Validation\ValidationException;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        $currency = Setting::getDefaultCurrency();

        return [
            Action::make('recordPayment')
                ->label('Record payment')
                ->icon('heroicon-o-banknotes')
                ->visible(fn (): bool => (float) $this->getRecord()->balance_due > 0)
                ->form([
                    Select::make('payment_type_id')
                        ->label('Payment type')
                        ->options(function (): array {
                            $branchId = $this->getRecord()->branch_id;

                            return PaymentType::query()
                                ->active()
                                ->forBranch($branchId)
                                ->where('is_accounts_receivable', false)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->required()
                        ->searchable()
                        ->preload(),
                    TextInput::make('amount')
                        ->label('Amount')
                        ->numeric()
                        ->required()
                        ->prefix($currency)
                        ->default(fn (): float => (float) $this->getRecord()->balance_due)
                        ->maxValue(fn (): float => (float) $this->getRecord()->balance_due)
                        ->minValue(0.01),
                ])
                ->action(function (array $data): void {
                    try {
                        app(AccountsReceivableService::class)->recordCollectionPayment(
                            $this->getRecord(),
                            (int) $data['payment_type_id'],
                            (float) $data['amount'],
                        );
                        $this->record->refresh();
                        Notification::make()
                            ->success()
                            ->title('Payment recorded')
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->danger()
                            ->title('Could not record payment')
                            ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                            ->send();
                    }
                }),
            Action::make('printReceipt')
                ->label('Print receipt')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('receipt.show', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }
}
