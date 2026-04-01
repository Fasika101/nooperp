<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmLeadResource\Pages;
use App\Filament\Resources\CrmLeadResource\RelationManagers\CrmLeadTasksRelationManager;
use App\Models\CrmLead;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CrmLeadResource extends Resource
{
    protected static ?string $model = CrmLead::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-plus';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Leads';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('company_name')->maxLength(255),
                TextInput::make('email')->email()->maxLength(255),
                TextInput::make('phone')->tel()->maxLength(255),
                TextInput::make('source')->maxLength(255)->placeholder('Web, referral, walk-in…'),
                Select::make('crm_lead_stage_id')
                    ->relationship('stage', 'name')
                    ->required()
                    ->preload()
                    ->searchable(),
                Select::make('assigned_user_id')
                    ->relationship('assignedUser', 'name')
                    ->preload()
                    ->searchable(),
                Select::make('customer_id')
                    ->relationship('customer', 'name')
                    ->preload()
                    ->searchable(),
                Textarea::make('notes')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('company_name')->searchable(),
                Tables\Columns\TextColumn::make('stage.name')->label('Stage')->badge(),
                Tables\Columns\TextColumn::make('assignedUser.name')->label('Owner')->placeholder('—'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->actions([EditAction::make()])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            CrmLeadTasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmLeads::route('/'),
            'create' => Pages\CreateCrmLead::route('/create'),
            'edit' => Pages\EditCrmLead::route('/{record}/edit'),
        ];
    }
}
