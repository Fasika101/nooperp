<?php

namespace App\Filament\Resources\WebsiteOrderResource\Pages;

use App\Filament\Resources\WebsiteOrderResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewWebsiteOrder extends ViewRecord
{
    protected static string $resource = WebsiteOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('confirm')
                ->label('Confirm order')
                ->color('warning')
                ->visible(fn (): bool => ($this->getRecord()->shipping_status ?? 'pending') === 'pending')
                ->action(function (): void {
                    $this->getRecord()->update(['shipping_status' => 'confirmed', 'status' => 'processing']);
                    Notification::make()->success()->title('Confirmed')->send();
                }),
            Action::make('ship')
                ->label('Mark shipped')
                ->color('info')
                ->action(function (): void {
                    $this->getRecord()->update(['shipping_status' => 'shipped', 'status' => 'processing']);
                    Notification::make()->success()->title('Shipped')->send();
                }),
            Action::make('deliver')
                ->label('Mark delivered')
                ->color('success')
                ->action(function (): void {
                    /** @var Order $order */
                    $order = $this->getRecord();
                    $order->update(['shipping_status' => 'delivered', 'status' => 'completed']);
                    Notification::make()->success()->title('Delivered')->send();
                }),
        ];
    }
}
