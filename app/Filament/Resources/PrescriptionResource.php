<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrescriptionResource\Pages;
use App\Models\Prescription;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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
                    ])
                    ->columns(3),
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
                    ])
                    ->columns(3),
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
                Tables\Columns\TextColumn::make('left_eye_sphere')
                    ->label('L Sphere'),
                Tables\Columns\TextColumn::make('left_eye_cylinder')
                    ->label('L Cyl'),
                Tables\Columns\TextColumn::make('left_eye_axis')
                    ->label('L Axis'),
                Tables\Columns\TextColumn::make('right_eye_sphere')
                    ->label('R Sphere'),
                Tables\Columns\TextColumn::make('right_eye_cylinder')
                    ->label('R Cyl'),
                Tables\Columns\TextColumn::make('right_eye_axis')
                    ->label('R Axis'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
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
            'edit' => Pages\EditPrescription::route('/{record}/edit'),
        ];
    }
}
