<?php

namespace App\Filament\Projects\Resources;

use App\Filament\Projects\Resources\ProjectResource\Pages;
use App\Filament\Projects\Resources\ProjectResource\RelationManagers\ProjectBugsRelationManager;
use App\Filament\Projects\Resources\ProjectResource\RelationManagers\ProjectCommentsRelationManager;
use App\Filament\Projects\Resources\ProjectResource\RelationManagers\ProjectExpensesRelationManager;
use App\Filament\Projects\Resources\ProjectResource\RelationManagers\ProjectFilesRelationManager;
use App\Filament\Projects\Resources\ProjectResource\RelationManagers\ProjectMilestonesRelationManager;
use App\Filament\Projects\Resources\ProjectResource\RelationManagers\ProjectTasksRelationManager;
use App\Models\Project;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static string|\UnitEnum|null $navigationGroup = 'Management';

    protected static ?string $navigationLabel = 'All Projects';

    protected static ?int $navigationSort = 30;

    /**
     * Returns true when the current user is allowed to edit project settings.
     * Used by form fields to lock themselves for non-creators.
     */
    public static function canEditSettings(?Project $project = null): bool
    {
        if (! $project) {
            return true; // Create form — always editable
        }

        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            return true;
        }

        return (int) $project->created_by === (int) $user->id;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(2)
                    ->columnSpan(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn (?Project $record) => ! static::canEditSettings($record))
                            ->dehydrated(),
                        Select::make('status')
                            ->options([
                                Project::STATUS_DRAFT     => 'Draft',
                                Project::STATUS_ACTIVE    => 'Active',
                                Project::STATUS_ON_HOLD   => 'On hold',
                                Project::STATUS_COMPLETED => 'Completed',
                                Project::STATUS_CANCELLED => 'Cancelled',
                            ])
                            ->required()
                            ->default(Project::STATUS_ACTIVE)
                            ->disabled(fn (?Project $record) => ! static::canEditSettings($record))
                            ->dehydrated(),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull()
                            ->disabled(fn (?Project $record) => ! static::canEditSettings($record))
                            ->dehydrated(),
                        Select::make('customer_id')
                            ->relationship('customer', 'name')
                            ->preload()
                            ->searchable()
                            ->disabled(fn (?Project $record) => ! static::canEditSettings($record))
                            ->dehydrated(),
                        TextInput::make('budget')
                            ->label('Budget')
                            ->numeric()
                            ->prefix('ETB')
                            ->minValue(0)
                            ->disabled(fn (?Project $record) => ! static::canEditSettings($record))
                            ->dehydrated(),
                        DatePicker::make('start_date')
                            ->disabled(fn (?Project $record) => ! static::canEditSettings($record))
                            ->dehydrated(),
                        DatePicker::make('end_date')
                            ->helperText('Deadline used for due alerts and the countdown timer.')
                            ->disabled(fn (?Project $record) => ! static::canEditSettings($record))
                            ->dehydrated(),
                        Select::make('member_ids')
                            ->label('Team members')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(fn () => User::query()->orderBy('name')->pluck('name', 'id'))
                            ->helperText('Creator is always kept on the team when you save.')
                            ->columnSpanFull()
                            ->disabled(fn (?Project $record) => ! static::canEditSettings($record))
                            ->dehydrated(),

                        \Filament\Forms\Components\Placeholder::make('readonly_notice')
                            ->label('')
                            ->content('You can view this project but only the creator can edit its settings.')
                            ->columnSpanFull()
                            ->visible(fn (?Project $record) => $record !== null && ! static::canEditSettings($record)),
                    ]),

                Section::make('Financials')
                    ->columnSpan(1)
                    ->schema([
                        Placeholder::make('total_expenses')
                            ->label('Total expenses')
                            ->content(fn (?Project $record) => $record
                                ? 'ETB ' . number_format($record->totalExpenses(), 2)
                                : '—'),
                        Placeholder::make('budget_remaining')
                            ->label('Budget remaining')
                            ->content(fn (?Project $record): string => match (true) {
                                $record === null                     => '—',
                                $record->budget === null             => 'No budget set',
                                default                              => 'ETB ' . number_format(
                                    (float) $record->budget - $record->totalExpenses(), 2
                                ),
                            }),
                        Placeholder::make('total_hours')
                            ->label('Total hours logged')
                            ->content(fn (?Project $record) => $record
                                ? $record->totalLoggedHours() . 'h'
                                : '—'),
                        Placeholder::make('task_count')
                            ->label('Tasks')
                            ->content(fn (?Project $record) => $record
                                ? $record->tasks()->count() . ' tasks'
                                : '—'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();

                // super_admin sees every project
                if ($user->hasRole('super_admin')) {
                    return $query;
                }

                // Everyone else only sees projects they created or are a member of
                $uid = $user->id;

                return $query->where(function ($q) use ($uid) {
                    $q->where('created_by', $uid)
                        ->orWhereHas('members', fn ($m) => $m->whereKey($uid));
                });
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('budget')
                    ->prefix('ETB ')
                    ->numeric(decimalPlaces: 0)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('creator.name')->label('Created by')->placeholder('—'),
                Tables\Columns\TextColumn::make('start_date')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('Deadline')
                    ->date()
                    ->placeholder('—')
                    ->color(fn (Project $record): string => match (true) {
                        $record->end_date === null => 'gray',
                        in_array($record->status, [Project::STATUS_COMPLETED, Project::STATUS_CANCELLED]) => 'gray',
                        $record->end_date->isPast() => 'danger',
                        $record->end_date->lte(now()->addDays(7)) => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([
                EditAction::make()
                    ->visible(fn (Project $record) => static::canEditSettings($record)),
                \Filament\Actions\ViewAction::make()
                    ->visible(fn (Project $record) => ! static::canEditSettings($record)),
                ReplicateAction::make()
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->visible(fn (Project $record) => static::canEditSettings($record))
                    ->beforeReplicaSaved(function (Project $replica): void {
                        $replica->name       = 'Copy of ' . $replica->name;
                        $replica->status     = Project::STATUS_DRAFT;
                        $replica->created_by = auth()->id();
                    })
                    ->successRedirectUrl(fn (Project $replica) => static::getUrl('edit', ['record' => $replica])),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->hasRole('super_admin')),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProjectTasksRelationManager::class,
            ProjectMilestonesRelationManager::class,
            ProjectBugsRelationManager::class,
            ProjectExpensesRelationManager::class,
            ProjectFilesRelationManager::class,
            ProjectCommentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProjects::route('/'),
            'create' => Pages\CreateProject::route('/create'),
            'edit'   => Pages\EditProject::route('/{record}/edit'),
        ];
    }
}
