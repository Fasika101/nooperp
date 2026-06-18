<?php

namespace App\Filament\Pages;

use App\Models\OpticalRxDiopterValue;
use App\Models\Setting;
use App\Support\OpticalRxConfig;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

class LensRxValuesSettingsPage extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Lens RX values & pricing';

    protected static ?string $title = 'Lens RX values & pricing';

    protected static ?int $navigationSort = 11;

    public ?array $data = [];

    public function mount(): void
    {
        OpticalRxConfig::ensureSeeded();

        $this->form->fill([
            'compound_sv_tier1_price' => OpticalRxConfig::getCompoundSvTier1Price() ?: '',
            'compound_sv_tier2_price' => OpticalRxConfig::getCompoundSvTier2Price() ?: '',
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set(OpticalRxConfig::COMPOUND_SV_TIER1_SETTING, (float) ($data['compound_sv_tier1_price'] ?? 0));
        Setting::set(OpticalRxConfig::COMPOUND_SV_TIER2_SETTING, (float) ($data['compound_sv_tier2_price'] ?? 0));

        Notification::make()->success()->title('Compound prices saved')->send();
    }

    protected function getHeaderActions(): array
    {
        $currency = Setting::getDefaultCurrency();

        return collect(OpticalRxDiopterValue::groupOptions())
            ->map(fn (string $label, string $group): Action => Action::make('configure_' . $group)
                ->label($label)
                ->icon('heroicon-o-pencil-square')
                ->modalHeading("Configure: {$label}")
                ->modalDescription('Built-in values (greyed out) cannot be removed. You can set their price to 0 to effectively disable the add-on.')
                ->modalWidth('2xl')
                ->fillForm(fn (): array => ['rows' => OpticalRxConfig::rowsForForm($group)])
                ->form([
                    Repeater::make('rows')
                        ->label(false)
                        ->schema($this->diopterRepeaterSchema($currency))
                        ->defaultItems(0)
                        ->addActionLabel('Add diopter value')
                        ->deletable(fn (array $state): bool => ! ($state['is_default'] ?? false))
                        ->reorderable(false)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data) use ($group): void {
                    OpticalRxConfig::saveFromForm([$group => $data['rows']]);
                    Notification::make()->success()->title('Values saved')->send();
                }))
            ->values()
            ->all();
    }

    /**
     * @return array<int, TextInput|Hidden>
     */
    protected function diopterRepeaterSchema(string $currency): array
    {
        return [
            Hidden::make('is_default')
                ->default(false),
            TextInput::make('value')
                ->label('Diopter')
                ->required()
                ->disabled(fn (Get $get): bool => (bool) $get('is_default'))
                ->dehydrated()
                ->helperText(fn (Get $get): ?string => $get('is_default')
                    ? 'Built-in value — cannot be removed.'
                    : 'Two decimals, e.g. 6.25 or -7.50')
                ->placeholder('e.g. 6.25'),
            TextInput::make('price')
                ->label('Add-on price')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->default(0)
                ->required()
                ->suffix($currency),
        ];
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema->components([
            Section::make('Single Vision — Compound Pricing')
                ->description('When a prescription has both SPH and CYL, the system uses a flat price based on these tiers instead of per-value lookup.')
                ->columns(2)
                ->schema([
                    TextInput::make('compound_sv_tier1_price')
                        ->label('Tier 1 price (mild compound)')
                        ->helperText('SPH 0.25–4.00 AND CYL 0.25–2.00')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix($currency)
                        ->placeholder('0.00'),

                    TextInput::make('compound_sv_tier2_price')
                        ->label('Tier 2 price (strong compound)')
                        ->helperText('SPH > 4.00 OR CYL > 2.00 (up to SPH 9.00 / CYL 4.00)')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix($currency)
                        ->placeholder('0.00'),
                ]),

            Section::make('SPH & CYL Diopter Values')
                ->description('Click any group below to view and edit its diopter values and per-value add-on prices. These apply when only SPH or only CYL is present (not compound).')
                ->schema([
                    Grid::make(2)
                        ->schema(collect(OpticalRxDiopterValue::groupOptions())
                            ->map(fn (string $label, string $group): \Filament\Schemas\Components\Component => \Filament\Schemas\Components\Section::make($label)
                                ->description(fn () => OpticalRxDiopterValue::query()
                                        ->where('group', $group)
                                        ->count() . ' values configured')
                                ->schema([
                                    \Filament\Schemas\Components\Actions::make([
                                        \Filament\Actions\Action::make('open_' . $group)
                                            ->label('Edit values')
                                            ->icon('heroicon-o-pencil')
                                            ->color('gray')
                                            ->size('sm')
                                            ->modalHeading("Configure: {$label}")
                                            ->modalDescription('Built-in values (greyed out) cannot be removed. Set price to 0 to disable the add-on for that value.')
                                            ->modalWidth('2xl')
                                            ->fillForm(fn (): array => ['rows' => OpticalRxConfig::rowsForForm($group)])
                                            ->form([
                                                Repeater::make('rows')
                                                    ->label(false)
                                                    ->schema($this->diopterRepeaterSchema($currency))
                                                    ->defaultItems(0)
                                                    ->addActionLabel('Add diopter value')
                                                    ->deletable(fn (array $state): bool => ! ($state['is_default'] ?? false))
                                                    ->reorderable(false)
                                                    ->columnSpanFull(),
                                            ])
                                            ->action(function (array $data) use ($group): void {
                                                OpticalRxConfig::saveFromForm([$group => $data['rows']]);
                                                Notification::make()->success()->title('Values saved')->send();
                                            }),
                                    ]),
                                ])
                                ->columnSpan(1))
                            ->values()
                            ->all()),
                ]),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    protected function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make($this->getFormActions())
                    ->alignment(Alignment::Start),
            ]);
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save compound prices')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }
}
