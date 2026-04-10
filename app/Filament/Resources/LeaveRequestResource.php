<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeaveRequestResource\Pages;
use App\Models\LeaveRequest;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'HR';

    protected static ?int $navigationSort = 12;

    protected static ?string $navigationLabel = 'Leave requests';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request')
                    ->schema([
                        Select::make('employee_id')
                            ->relationship('employee', 'full_name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('leave_type')
                            ->options(LeaveRequest::leaveTypeOptions())
                            ->required()
                            ->native(false),
                        DatePicker::make('start_date')
                            ->required()
                            ->native(false),
                        DatePicker::make('end_date')
                            ->required()
                            ->native(false)
                            ->afterOrEqual('start_date'),
                        Textarea::make('reason')
                            ->rows(3)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->options(LeaveRequest::statusOptions())
                            ->required()
                            ->native(false)
                            ->default(LeaveRequest::STATUS_PENDING)
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                        Textarea::make('review_notes')
                            ->label('Review / rejection notes')
                            ->rows(2)
                            ->columnSpanFull()
                            ->visible(fn (string $operation): bool => $operation === 'edit'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['employee', 'reviewer']))
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('leave_type')
                    ->formatStateUsing(fn (?string $state): string => LeaveRequest::leaveTypeOptions()[$state] ?? (string) $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('start_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('end_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('days_count')
                    ->label('Days')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        LeaveRequest::STATUS_APPROVED => 'success',
                        LeaveRequest::STATUS_REJECTED => 'danger',
                        LeaveRequest::STATUS_CANCELLED => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('reviewer.name')
                    ->label('Reviewer')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options(LeaveRequest::statusOptions()),
                Tables\Filters\SelectFilter::make('leave_type')
                    ->options(LeaveRequest::leaveTypeOptions()),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (LeaveRequest $record): bool => $record->status === LeaveRequest::STATUS_PENDING)
                    ->action(function (LeaveRequest $record): void {
                        $record->update([
                            'status' => LeaveRequest::STATUS_APPROVED,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'review_notes' => null,
                        ]);
                        Notification::make()->success()->title('Leave approved')->send();
                    }),
                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (LeaveRequest $record): bool => $record->status === LeaveRequest::STATUS_PENDING)
                    ->form([
                        Textarea::make('review_notes')
                            ->label('Reason')
                            ->required(),
                    ])
                    ->action(function (LeaveRequest $record, array $data): void {
                        $record->update([
                            'status' => LeaveRequest::STATUS_REJECTED,
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                            'review_notes' => $data['review_notes'],
                        ]);
                        Notification::make()->title('Leave rejected')->warning()->send();
                    }),
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
            'index' => Pages\ListLeaveRequests::route('/'),
            'create' => Pages\CreateLeaveRequest::route('/create'),
            'edit' => Pages\EditLeaveRequest::route('/{record}/edit'),
        ];
    }
}
