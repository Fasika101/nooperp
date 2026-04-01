<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CrmDealResource\Pages;
use App\Models\CrmDeal;
use App\Models\Setting;
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

class CrmDealResource extends Resource
{
    protected static ?string $model = CrmDeal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|\UnitEnum|null $navigationGroup = 'CRM';

    protected static ?string $navigationLabel = 'Deals';

    protected static ?int $navigationSort = 11;

    public static function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('amount')
                    ->numeric()
                    ->prefix($currency)
                    ->minValue(0),
                Select::make('crm_deal_stage_id')
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
                Select::make('crm_lead_id')
                    ->relationship('lead', 'title')
                    ->preload()
                    ->searchable(),
                Select::make('project_id')
                    ->relationship('project', 'name')
                    ->preload()
                    ->searchable()
                    ->label('Linked project'),
                Textarea::make('notes')
                    ->rows(4)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $currency = Setting::getDefaultCurrency();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('amount')->money($currency)->sortable(),
                Tables\Columns\TextColumn::make('stage.name')->label('Stage')->badge(),
                Tables\Columns\TextColumn::make('project.name')->label('Project')->placeholder('—'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmDeals::route('/'),
            'create' => Pages\CreateCrmDeal::route('/create'),
            'edit' => Pages\EditCrmDeal::route('/{record}/edit'),
        ];
    }
}
