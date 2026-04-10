<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Imports\ProductImporter;
use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('downloadImportTemplate')
                ->label('Download import template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(fn () => ProductImporter::downloadTemplateCsv()),
            Actions\ImportAction::make()
                ->label('Import products (CSV)')
                ->importer(ProductImporter::class)
                ->closeModalByClickingAway(false),
        ];
    }
}
