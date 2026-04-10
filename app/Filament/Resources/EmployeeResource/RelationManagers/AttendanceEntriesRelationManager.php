<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\AttendanceEntry;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class AttendanceEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendanceEntries';

    protected static ?string $title = 'Attendance';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('work_date')
                    ->required()
                    ->default(today()),
                Select::make('status')
                    ->options(AttendanceEntry::statusOptions())
                    ->required()
                    ->native(false)
                    ->default(AttendanceEntry::STATUS_PRESENT),
                TimePicker::make('time_in')->seconds(false),
                TimePicker::make('time_out')->seconds(false),
                Textarea::make('notes')->rows(2)->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('work_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('time_in')->placeholder('—'),
                Tables\Columns\TextColumn::make('time_out')->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): AttendanceEntry {
                        $employeeId = $this->getOwnerRecord()->getKey();
                        $exists = AttendanceEntry::query()
                            ->where('employee_id', $employeeId)
                            ->whereDate('work_date', $data['work_date'])
                            ->exists();

                        if ($exists) {
                            throw ValidationException::withMessages([
                                'work_date' => __('An entry for this date already exists.'),
                            ]);
                        }

                        $data['employee_id'] = $employeeId;

                        return AttendanceEntry::query()->create($data);
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->using(function (AttendanceEntry $record, array $data): AttendanceEntry {
                        $duplicate = AttendanceEntry::query()
                            ->where('employee_id', $record->employee_id)
                            ->whereDate('work_date', $data['work_date'])
                            ->whereKeyNot($record->getKey())
                            ->exists();

                        if ($duplicate) {
                            throw ValidationException::withMessages([
                                'work_date' => __('Another entry exists for this date.'),
                            ]);
                        }

                        $record->update($data);

                        return $record;
                    }),
            ])
            ->defaultSort('work_date', 'desc');
    }
}
