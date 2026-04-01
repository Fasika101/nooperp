<?php

namespace App\Filament\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Project;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Projects';

    protected static ?string $navigationLabel = 'My projects';

    protected static ?string $title = 'My projects';

    protected static ?int $navigationSort = 1;

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
            ->heading('Projects you created or are a member of')
            ->query($this->myProjectsQuery())
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Project $record): string => ProjectResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('status')->badge(),
                TextColumn::make('customer.name')->placeholder('—'),
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
