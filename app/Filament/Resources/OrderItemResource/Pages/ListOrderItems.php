<?php

namespace App\Filament\Resources\OrderItemResource\Pages;

use App\Filament\Resources\OrderItemResource;
use App\Models\OrderItem;
use App\Services\SoldItemsExporter;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListOrderItems extends ListRecords
{
    protected static string $resource = OrderItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->action(fn () => $this->exportExcel()),
            Actions\CreateAction::make(),
        ];
    }

    protected function exportExcel(): StreamedResponse
    {
        $filters = $this->tableFilters ?? [];

        $query = OrderItem::query()->with(['order.customer', 'product', 'rxExtraCustomizations']);

        // Apply item_type filter
        $type = data_get($filters, 'item_type.value') ?? data_get($filters, 'item_type');
        match ($type) {
            'frames' => $query->where(function (Builder $q): void {
                $q->whereNull('optical_meta')->orWhere('optical_meta', '[]');
            }),
            'lenses' => $query->whereNotNull('optical_meta')->where('optical_meta', '!=', '[]'),
            default  => null,
        };

        // Apply date range filter
        $from  = data_get($filters, 'date_range.from');
        $until = data_get($filters, 'date_range.until');
        if (filled($from) && filled($until)) {
            $query->whereBetween('created_at', [
                Carbon::parse($from)->startOfDay(),
                Carbon::parse($until)->endOfDay(),
            ]);
        }

        $rows = $query->orderByDesc('created_at')->get();

        return (new SoldItemsExporter)->download($rows, $filters);
    }
}
