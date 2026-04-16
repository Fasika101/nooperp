<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Filament\Resources\StockPurchaseResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        $record = static::getResource()::resolveRecordRouteBinding($key, function (Builder $query): Builder {
            return $query->with([
                'category',
                'brand',
                'size',
                'color',
                'gender',
                'material',
                'shape',
                'attachedProductOptions',
                'branchStocks.branch',
                'branchStocks.productVariant.colorOption',
                'branchStocks.productVariant.sizeOption',
            ]);
        });

        if ($record === null) {
            throw (new ModelNotFoundException)->setModel(static::getModel(), [(string) $key]);
        }

        return $record;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('restock')
                ->label('Restock')
                ->icon('heroicon-o-arrow-path')
                ->url(fn (): string => StockPurchaseResource::getUrl('create', ['product_id' => $this->getRecord()->getKey()]))
                ->color('success'),
            EditAction::make(),
        ];
    }
}
