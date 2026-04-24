<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrescriptionResource\Pages;
use App\Models\Prescription;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PrescriptionResource extends Resource
{
    protected static ?string $model = Prescription::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static string|\UnitEnum|null $navigationGroup = 'Optical';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Select::make('vision')
                    ->options([
                        'single' => 'Single Vision',
                        'progressive' => 'Progressive',
                    ])
                    ->required(),
                Section::make('Left Eye')
                    ->schema([
                        TextInput::make('left_eye_sphere')
                            ->numeric()
                            ->step(0.25),
                        TextInput::make('left_eye_cylinder')
                            ->numeric()
                            ->step(0.25),
                        TextInput::make('left_eye_axis')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(180),
                        TextInput::make('left_eye_add')
                            ->numeric()
                            ->step(0.25)
                            ->visible(fn ($get) => $get('vision') === 'progressive'),
                    ])
                    ->columns(fn ($get) => $get('vision') === 'progressive' ? 4 : 3),
                Section::make('Right Eye')
                    ->schema([
                        TextInput::make('right_eye_sphere')
                            ->numeric()
                            ->step(0.25),
                        TextInput::make('right_eye_cylinder')
                            ->numeric()
                            ->step(0.25),
                        TextInput::make('right_eye_axis')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(180),
                        TextInput::make('right_eye_add')
                            ->numeric()
                            ->step(0.25)
                            ->visible(fn ($get) => $get('vision') === 'progressive'),
                    ])
                    ->columns(fn ($get) => $get('vision') === 'progressive' ? 4 : 3),
                Section::make('Pupillary Distance (PD)')
                    ->schema([
                        Select::make('pd_mode')
                            ->label('PD Mode')
                            ->options([
                                'one' => 'Single PD',
                                'two' => 'Dual PD',
                            ])
                            ->live(),
                        TextInput::make('pd_single')
                            ->label('PD')
                            ->numeric()
                            ->visible(fn ($get) => $get('pd_mode') === 'one'),
                        TextInput::make('pd_right')
                            ->label('PD Right')
                            ->numeric()
                            ->visible(fn ($get) => $get('pd_mode') === 'two'),
                        TextInput::make('pd_left')
                            ->label('PD Left')
                            ->numeric()
                            ->visible(fn ($get) => $get('pd_mode') === 'two'),
                    ])
                    ->columns(2),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('customer.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vision')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'progressive' => 'warning',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('left_eye_sphere')
                    ->label('L Sph'),
                Tables\Columns\TextColumn::make('left_eye_cylinder')
                    ->label('L Cyl'),
                Tables\Columns\TextColumn::make('right_eye_sphere')
                    ->label('R Sph'),
                Tables\Columns\TextColumn::make('right_eye_cylinder')
                    ->label('R Cyl'),
                Tables\Columns\TextColumn::make('orderItem.order.id')
                    ->label('Order #')
                    ->url(fn ($record) => $record->orderItem?->order_id ? route('filament.admin.resources.orders.view', $record->orderItem->order_id) : null)
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn ($record) => route('prescription.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrescriptions::route('/'),
            'create' => Pages\CreatePrescription::route('/create'),
            'view' => Pages\ViewPrescription::route('/{record}'),
            'edit' => Pages\EditPrescription::route('/{record}/edit'),
        ];
    }
}
