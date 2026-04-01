<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\ProjectTask;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Projects';

    protected static ?string $navigationLabel = 'My tasks';

    protected static ?string $title = 'My tasks';

    protected static ?int $navigationSort = 2;

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
                TextColumn::make('title')
                    ->searchable()
                    ->url(fn (ProjectTask $record): string => ProjectResource::getUrl('edit', ['record' => $record->project_id])),
                TextColumn::make('project.name')->label('Project')->sortable(),
                TextColumn::make('stage.name')->badge(),
                TextColumn::make('priority')->badge(),
                TextColumn::make('due_date')->date()->placeholder('—')->sortable(),
                TextColumn::make('visibility')
                    ->label('Why listed')
                    ->getStateUsing(function (ProjectTask $record): string {
                        $uid = auth()->id();
                        if ($record->assignees->pluck('id')->contains($uid)) {
                            return 'Assigned';
                        }

                        return 'Project team';
                    }),
            ])
            ->defaultSort('due_date');
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
