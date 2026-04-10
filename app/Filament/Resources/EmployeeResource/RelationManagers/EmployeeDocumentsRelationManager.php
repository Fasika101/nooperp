<?php

namespace App\Filament\Resources\EmployeeResource\RelationManagers;

use App\Models\EmployeeDocument;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class EmployeeDocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'HR documents';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Select::make('document_type')
                    ->options(EmployeeDocument::documentTypeOptions())
                    ->required()
                    ->native(false),
                FileUpload::make('file_path')
                    ->label('File')
                    ->disk('public')
                    ->directory('employee-documents')
                    ->required()
                    ->columnSpanFull(),
                DatePicker::make('expires_at')
                    ->label('Expires on'),
                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable(),
                Tables\Columns\TextColumn::make('document_type')
                    ->formatStateUsing(fn (?string $state): string => EmployeeDocument::documentTypeOptions()[$state] ?? (string) $state),
                Tables\Columns\TextColumn::make('expires_at')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label('Uploaded'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
