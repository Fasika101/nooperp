<?php

namespace App\Filament\Projects\Resources;

use App\Filament\Projects\Resources\CalendarEventResource\Pages;
use App\Models\CalendarEvent;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class CalendarEventResource extends Resource
{
    protected static ?string $model = CalendarEvent::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static string|\UnitEnum|null $navigationGroup = 'My Work';

    protected static ?string $navigationLabel = 'My Events';

    protected static ?int $navigationSort = 35;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),

                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),

                TextInput::make('location')
                    ->label('Location / Link')
                    ->maxLength(255)
                    ->placeholder('Room, address, or meeting link')
                    ->columnSpanFull(),

                Section::make('Date & Time')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Start date')
                            ->required()
                            ->live()
                            ->hidden(fn () => (bool) request()->query('date'))
                            ->minDate(now()),

                        DatePicker::make('end_date')
                            ->label('End date (optional)')
                            ->helperText('Leave blank for a single-day event.')
                            ->minDate(fn ($get) => $get('start_date')
                                ? \Carbon\Carbon::parse($get('start_date'))->addDay()
                                : now()->addDay()),

                        Toggle::make('all_day')
                            ->label('All-day event')
                            ->default(true)
                            ->live()
                            ->columnSpanFull()
                            ->afterStateHydrated(function (Toggle $component, ?CalendarEvent $record): void {
                                $component->state($record ? $record->isAllDay() : true);
                            })
                            ->dehydrated(false),

                        TimePicker::make('start_time')
                            ->label('Start time')
                            ->seconds(false)
                            ->visible(fn ($get) => ! $get('all_day')),

                        TimePicker::make('end_time')
                            ->label('End time')
                            ->seconds(false)
                            ->visible(fn ($get) => ! $get('all_day')),
                    ]),

                Select::make('color')
                    ->label('Color')
                    ->options(CalendarEvent::colorOptions())
                    ->default('violet')
                    ->required(),

                Select::make('attendee_ids')
                    ->label('Invite employees')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->options(fn () => User::query()
                        ->where('id', '!=', auth()->id())
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->helperText('Invited employees will see this event on their calendar.')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($query) {
                $user = auth()->user();

                // super_admin sees all events from all employees
                if ($user->hasRole('super_admin')) {
                    return $query;
                }

                $uid = $user->id;

                return $query->where(function ($q) use ($uid) {
                    $q->where('created_by', $uid)
                        ->orWhereHas('attendees', fn ($a) => $a->whereKey($uid));
                });
            })
            ->columns([
                Tables\Columns\ColorColumn::make('color')
                    ->label('')
                    ->getStateUsing(fn (CalendarEvent $r) => $r->hexColor())
                    ->width('32px'),
                Tables\Columns\TextColumn::make('title')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('start_date')->date()->sortable(),
                Tables\Columns\TextColumn::make('end_date')->date()->placeholder('—'),
                Tables\Columns\TextColumn::make('location')->placeholder('—')->limit(40),
                Tables\Columns\TextColumn::make('attendees.name')
                    ->label('Attendees')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Created by')
                    ->badge()
                    ->color('gray'),
            ])
            ->defaultSort('start_date')
            ->actions([
                EditAction::make()
                    ->visible(fn (CalendarEvent $r) => auth()->user()->hasRole('super_admin')
                        || (int) $r->created_by === (int) auth()->id()),
                DeleteAction::make()
                    ->visible(fn (CalendarEvent $r) => auth()->user()->hasRole('super_admin')
                        || (int) $r->created_by === (int) auth()->id()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCalendarEvents::route('/'),
            'create' => Pages\CreateCalendarEvent::route('/create'),
            'edit'   => Pages\EditCalendarEvent::route('/{record}/edit'),
        ];
    }
}
