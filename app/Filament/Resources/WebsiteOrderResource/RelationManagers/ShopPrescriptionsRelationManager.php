<?php

namespace App\Filament\Resources\WebsiteOrderResource\RelationManagers;

use App\Models\Setting;
use App\Models\ShopPrescription;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class ShopPrescriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'shopPrescriptions';

    protected static ?string $title = 'Prescription scans';

    public function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\ImageColumn::make('path')
                    ->label('Photo')
                    ->disk('public')
                    ->height(72)
                    ->width(72)
                    ->square(),
                Tables\Columns\TextColumn::make('vision_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? ucfirst($state) : '—'),
                Tables\Columns\TextColumn::make('confidence')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'high' => 'success',
                        'medium' => 'warning',
                        'low' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('prescription_price')
                    ->label('Rx price')
                    ->money($currency),
                Tables\Columns\TextColumn::make('scan_summary')
                    ->label('Scanned values')
                    ->getStateUsing(function (ShopPrescription $record): HtmlString {
                        $s = $record->scan_json ?? [];
                        $r = $s['right_eye'] ?? [];
                        $l = $s['left_eye'] ?? [];
                        $pd = $s['pd'] ?? [];

                        $fmtEye = function (array $eye, string $label): string {
                            $parts = array_filter([
                                isset($eye['sph']) ? 'SPH '.$eye['sph'] : null,
                                isset($eye['cyl']) ? 'CYL '.$eye['cyl'] : null,
                                isset($eye['axis']) ? 'AXIS '.$eye['axis'] : null,
                                isset($eye['add']) ? 'ADD '.$eye['add'] : null,
                            ]);

                            return '<strong>'.$label.'</strong>: '.(count($parts) ? implode(' · ', $parts) : '—');
                        };

                        $pdText = '—';
                        if (($pd['type'] ?? '') === 'one' && isset($pd['one'])) {
                            $pdText = (string) $pd['one'];
                        } elseif (($pd['type'] ?? '') === 'two') {
                            $pdText = trim(($pd['right'] ?? '?').' / '.($pd['left'] ?? '?'));
                        }

                        $notes = filled($record->scan_notes)
                            ? '<div class="mt-1 text-xs opacity-70">'.e($record->scan_notes).'</div>'
                            : '';

                        $html = $fmtEye($r, 'OD').'<br>'.$fmtEye($l, 'OS').'<br><strong>PD</strong>: '.$pdText.$notes;

                        return new HtmlString($html);
                    })
                    ->html()
                    ->wrap(),
                Tables\Columns\TextColumn::make('original_name')
                    ->label('File')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->url(fn (ShopPrescription $record): ?string => $record->publicUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (ShopPrescription $record): bool => filled($record->path)),
            ])
            ->paginated(false)
            ->emptyStateHeading('No prescription scans')
            ->emptyStateDescription('Scans from the in-app shop appear here after checkout.');
    }
}
