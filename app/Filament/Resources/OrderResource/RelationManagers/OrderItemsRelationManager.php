<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use App\Models\OrderItem;
use App\Models\Setting;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'orderItems';

    protected static ?string $title = 'Order items';

    public function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->recordTitleAttribute('id')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['product.size', 'product.color']))
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                Tables\Columns\ImageColumn::make('product.image')
                    ->label('Photo')
                    ->disk('public')
                    ->height(48)
                    ->width(48)
                    ->defaultImageUrl(fn (?OrderItem $record) => ($record?->hasOpticalDetails() ?? false)
                        ? 'https://ui-avatars.com/api/?name=LX&color=0F766E&background=CCFBF1'
                        : 'https://ui-avatars.com/api/?name=P&color=7F9CF5&background=EBF4FF')
                    ->extraImgAttributes(['class' => 'rounded-md object-cover']),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Product / item')
                    ->wrap()
                    ->searchable(),
                Tables\Columns\TextColumn::make('resolved_size')
                    ->label('Size')
                    ->placeholder('—')
                    ->getStateUsing(fn (OrderItem $record): string => (string) ($record->frameSize?->name ?? $record->product?->size?->name ?? '—')),
                Tables\Columns\TextColumn::make('resolved_color')
                    ->label('Color')
                    ->placeholder('—')
                    ->getStateUsing(fn (OrderItem $record): string => (string) ($record->frameColor?->name ?? $record->product?->color?->name ?? '—')),
                Tables\Columns\TextColumn::make('price')
                    ->label('Unit price')
                    ->money($currency),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty'),
                Tables\Columns\TextColumn::make('lens_type_summary')
                    ->label('Lens type')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('prescription_display')
                    ->label('Prescription')
                    ->getStateUsing(fn (OrderItem $record) => $record->getPrescriptionAdminHtml() ?? new \Illuminate\Support\HtmlString('<span class="text-gray-500 dark:text-gray-400">—</span>'))
                    ->html()
                    ->wrap(),
                Tables\Columns\TextColumn::make('line_subtotal')
                    ->label('Line total')
                    ->money($currency)
                    ->getStateUsing(fn (OrderItem $record): float => (float) $record->quantity * (float) $record->price),
            ])
            ->paginated(false);
    }
}
