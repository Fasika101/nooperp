<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AttendanceEntryResource\Pages;
use App\Models\AttendanceEntry;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceEntryResource extends Resource
{
    protected static ?string $model = AttendanceEntry::class;

    protected static ?string $modelLabel = 'attendance entry';

    protected static ?string $pluralModelLabel = 'Attendance';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static string|\UnitEnum|null $navigationGroup = 'HR';

    protected static ?int $navigationSort = 13;

    protected static ?string $navigationLabel = 'Attendance';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Daily attendance')
                    ->schema([
                        Select::make('employee_id')
                            ->relationship('employee', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        DatePicker::make('work_date')
                            ->required()
                            ->native(false)
                            ->default(today()),
                        Select::make('status')
                            ->options(AttendanceEntry::statusOptions())
                            ->required()
                            ->native(false)
                            ->default(AttendanceEntry::STATUS_PRESENT),
                        TimePicker::make('time_in')
                            ->label('Time in')
                            ->seconds(false),
                        TimePicker::make('time_out')
                            ->label('Time out')
                            ->seconds(false),
                        Textarea::make('notes')
                            ->rows(2)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('employee'))
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('work_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge(),
                Tables\Columns\TextColumn::make('time_in')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('time_out')
                    ->placeholder('—'),
            ])
            ->defaultSort('work_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(AttendanceEntry::statusOptions()),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceEntries::route('/'),
            'create' => Pages\CreateAttendanceEntry::route('/create'),
            'edit' => Pages\EditAttendanceEntry::route('/{record}/edit'),
        ];
    }
}
