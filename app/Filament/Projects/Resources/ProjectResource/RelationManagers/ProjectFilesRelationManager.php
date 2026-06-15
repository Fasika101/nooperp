<?php

namespace App\Filament\Projects\Resources\ProjectResource\RelationManagers;

use App\Models\ProjectFile;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ProjectFilesRelationManager extends RelationManager
{
    protected static string $relationship = 'files';

    protected static ?string $title = 'Files';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('file_path')
                    ->label('File')
                    ->required()
                    ->disk('public')
                    ->directory(fn () => 'project-files/' . $this->getOwnerRecord()->id)
                    ->preserveFilenames()
                    ->maxSize(20480)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Description (optional)')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('file_name')
                    ->label('File')
                    ->searchable()
                    ->description(fn (ProjectFile $r) => $r->description ? str($r->description)->limit(60) : null),
                Tables\Columns\TextColumn::make('size')
                    ->label('Size')
                    ->getStateUsing(fn (ProjectFile $r) => $r->getFormattedSize()),
                Tables\Columns\TextColumn::make('uploader.name')
                    ->label('Uploaded by')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data): ProjectFile {
                        $data['uploaded_by'] = auth()->id();
                        $data['file_name']   = basename($data['file_path']);
                        // Capture file size if the file exists on disk
                        if (Storage::disk('public')->exists($data['file_path'])) {
                            $data['file_size'] = Storage::disk('public')->size($data['file_path']);
                            $data['mime_type'] = Storage::disk('public')->mimeType($data['file_path']);
                        }

                        return $this->getOwnerRecord()->files()->create($data);
                    }),
            ])
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (ProjectFile $r) => Storage::disk('public')->url($r->file_path))
                    ->openUrlInNewTab(),
                DeleteAction::make()
                    ->using(function (ProjectFile $record): void {
                        // Remove file from disk before deleting DB record
                        if (Storage::disk('public')->exists($record->file_path)) {
                            Storage::disk('public')->delete($record->file_path);
                        }
                        $record->delete();
                    }),
            ]);
    }
}
