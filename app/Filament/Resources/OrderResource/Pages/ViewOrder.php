<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('printReceipt')
                ->label('Print receipt')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('receipt.show', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }
}
