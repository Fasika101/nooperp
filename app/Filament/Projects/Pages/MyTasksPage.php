<?php

namespace App\Filament\Projects\Pages;

use App\Filament\Projects\Resources\ProjectResource;
use App\Filament\Projects\Resources\ProjectTaskResource;
use App\Models\ProjectTask;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyTasksPage extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'My Work';

    protected static ?string $navigationLabel = 'My Tasks';

    protected static ?string $title = 'My Tasks';

    protected static ?int $navigationSort = 20;

    public function mount(): void
    {
        $this->bootedInteractsWithTable();
        $this->mountInteractsWithTable();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Tasks assigned to you, or on projects you participate in')
            ->query($this->myTasksQuery())
            ->columns([
                IconColumn::make('is_starred')
                    ->label('')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->width('36px'),
                TextColumn::make('title')
                    ->searchable()
                    ->url(fn (ProjectTask $record): string => ProjectTaskResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('project.name')->label('Project')->sortable(),
                TextColumn::make('stage.name')->badge(),
                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'urgent' => 'danger',
                        'high'   => 'warning',
                        'normal' => 'info',
                        default  => 'gray',
                    }),
                TextColumn::make('progress')
                    ->formatStateUsing(fn (int $state): string => "{$state}%")
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 100 => 'success',
                        $state >= 50   => 'info',
                        $state > 0     => 'warning',
                        default        => 'gray',
                    }),
                TextColumn::make('due_date')
                    ->date()
                    ->placeholder('—')
                    ->sortable()
                    ->color(fn (ProjectTask $record): string => match (true) {
                        $record->due_date === null              => 'gray',
                        $record->due_date->isPast()             => 'danger',
                        $record->due_date->lte(now()->addDays(3)) => 'warning',
                        default                                => 'success',
                    }),
            ])
            ->defaultSort('is_starred', 'desc')
            ->actions([
                Action::make('toggle_star')
                    ->label(fn (ProjectTask $record) => $record->is_starred ? 'Unstar' : 'Star')
                    ->icon(fn (ProjectTask $record) => $record->is_starred ? 'heroicon-s-star' : 'heroicon-o-star')
                    ->color(fn (ProjectTask $record) => $record->is_starred ? 'warning' : 'gray')
                    ->action(fn (ProjectTask $record) => $record->update(['is_starred' => ! $record->is_starred])),
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (ProjectTask $record) => ProjectTaskResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    protected function myTasksQuery(): Builder
    {
        $uid = auth()->id();

        return ProjectTask::query()
            ->with(['project', 'stage', 'assignees'])
            ->where(function (Builder $q) use ($uid): void {
                $q->whereHas('assignees', fn (Builder $aq) => $aq->whereKey($uid))
                    ->orWhereHas('project', function (Builder $pq) use ($uid): void {
                        $pq->where('created_by', $uid)
                            ->orWhereHas('members', fn (Builder $mq) => $mq->whereKey($uid));
                    });
            });
    }
}
