<?php

namespace App\Filament\Exports;

use App\Models\OrderItem;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class OrderItemExporter extends Exporter
{
    protected static ?string $model = OrderItem::class;

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['order.customer', 'product']);
    }

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('order.id')
                ->label('Order'),
            ExportColumn::make('order.customer.name')
                ->label('Customer'),
            ExportColumn::make('product.name')
                ->label('Product'),
            ExportColumn::make('quantity'),
            ExportColumn::make('price'),
            ExportColumn::make('subtotal')
                ->state(fn (OrderItem $record): string => number_format($record->quantity * $record->price, 2)),
            ExportColumn::make('order.discount_amount')
                ->label('Discount'),
            ExportColumn::make('revenue')
                ->label('Revenue')
                ->state(fn (OrderItem $record): string => number_format((float) $record->order->total_amount - (float) $record->order->shipping_amount, 2)),
            ExportColumn::make('order.shipping_amount')
                ->label('Shipping'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your sold items export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
