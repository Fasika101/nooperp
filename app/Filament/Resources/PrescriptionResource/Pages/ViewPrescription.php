<?php

namespace App\Filament\Resources\PrescriptionResource\Pages;

use App\Filament\Resources\PrescriptionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewPrescription extends ViewRecord
{
    protected static string $resource = PrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('print')
                ->label('Print Prescription')
                ->icon('heroicon-o-printer')
                ->color('info')
                ->url(fn ($record) => route('prescription.print', $record))
                ->openUrlInNewTab(),
        ];
    }
}
