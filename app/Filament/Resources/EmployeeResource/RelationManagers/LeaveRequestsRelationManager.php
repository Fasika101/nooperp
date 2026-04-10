<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\LeaveRequest;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class LeaveRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'leaveRequests';

    protected static ?string $title = 'Leave requests';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('leave_type')
                    ->options(LeaveRequest::leaveTypeOptions())
                    ->required()
                    ->native(false),
                DatePicker::make('start_date')->required(),
                DatePicker::make('end_date')->required()->afterOrEqual('start_date'),
                Textarea::make('reason')->rows(3)->columnSpanFull(),
                Select::make('status')
                    ->options(LeaveRequest::statusOptions())
                    ->required()
                    ->native(false),
                Textarea::make('review_notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('leave_type')
                    ->formatStateUsing(fn (?string $state): string => LeaveRequest::leaveTypeOptions()[$state] ?? (string) $state),
                Tables\Columns\TextColumn::make('start_date')->date(),
                Tables\Columns\TextColumn::make('end_date')->date(),
                Tables\Columns\TextColumn::make('days_count')->alignEnd(),
                Tables\Columns\TextColumn::make('status')->badge(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): LeaveRequest {
                        $data['employee_id'] = $this->getOwnerRecord()->getKey();
                        $data['status'] = LeaveRequest::STATUS_PENDING;
                        $data['reviewed_by'] = null;
                        $data['reviewed_at'] = null;
                        $data['review_notes'] = null;

                        return LeaveRequest::query()->create($data);
                    }),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->defaultSort('start_date', 'desc');
    }
}
