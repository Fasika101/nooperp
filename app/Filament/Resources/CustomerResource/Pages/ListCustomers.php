<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Imports\CustomerImporter;
use App\Filament\Resources\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('importResults')
                ->label('Import results')
                ->icon('heroicon-o-chart-bar-square')
                ->url(CustomerResource::getUrl('import-results')),
            Actions\Action::make('downloadImportTemplate')
                ->label('Download sample template (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn () => CustomerImporter::downloadExampleCsv()),
            Actions\ImportAction::make()
                ->label('Import customers (CSV)')
                ->importer(CustomerImporter::class),
        ];
    }
}
