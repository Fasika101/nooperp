<?php

namespace App\Filament\Projects\Pages;

use App\Filament\Projects\Resources\ProjectResource;
use App\Models\Project;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MyProjectsPage extends Page implements HasTable
{
    use HasPageShield;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'My Work';

    protected static ?string $navigationLabel = 'My Projects';

    protected static ?string $title = 'My Projects';

    protected static ?int $navigationSort = 10;

    public function mount(): void
    {
        $this->bootedInteractsWithTable();
        $this->mountInteractsWithTable();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_project')
                ->label('New Project')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(ProjectResource::getUrl('create')),
        ];
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
            ->heading('Projects you created or are a member of')
            ->query($this->myProjectsQuery())
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Project $record): string => ProjectResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('status')->badge(),
                TextColumn::make('customer.name')->placeholder('—'),
                TextColumn::make('end_date')
                    ->label('Deadline')
                    ->date()
                    ->placeholder('No deadline')
                    ->color(fn (Project $record): string => match (true) {
                        $record->end_date === null => 'gray',
                        in_array($record->status, [Project::STATUS_COMPLETED, Project::STATUS_CANCELLED]) => 'gray',
                        $record->end_date->isPast() => 'danger',
                        $record->end_date->lte(now()->addDays(7)) => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('role')
                    ->label('Your role')
                    ->getStateUsing(function (Project $record): string {
                        if ((int) $record->created_by === (int) auth()->id()) {
                            return 'Creator';
                        }

                        return 'Member';
                    }),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    protected function myProjectsQuery(): Builder
    {
        $uid = auth()->id();

        return Project::query()
            ->where(function (Builder $q) use ($uid): void {
                $q->where('created_by', $uid)
                    ->orWhereHas('members', fn (Builder $mq) => $mq->whereKey($uid));
            });
    }
}
