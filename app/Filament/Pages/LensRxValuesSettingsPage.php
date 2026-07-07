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
            'progressive_add_tier1_price' => OpticalRxConfig::getProgressiveAddTier1Price() ?: '',
            'progressive_add_tier2_price' => OpticalRxConfig::getProgressiveAddTier2Price() ?: '',
            'progressive_negative_sph_price' => OpticalRxConfig::getProgressiveNegativeSphPrice() ?: '',
            'progressive_cyl_price' => OpticalRxConfig::getProgressiveCylPrice() ?: '',
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set(OpticalRxConfig::COMPOUND_SV_TIER1_SETTING, (float) ($data['compound_sv_tier1_price'] ?? 0));
        Setting::set(OpticalRxConfig::COMPOUND_SV_TIER2_SETTING, (float) ($data['compound_sv_tier2_price'] ?? 0));
        Setting::set(OpticalRxConfig::PROGRESSIVE_ADD_TIER1_SETTING, (float) ($data['progressive_add_tier1_price'] ?? 0));
        Setting::set(OpticalRxConfig::PROGRESSIVE_ADD_TIER2_SETTING, (float) ($data['progressive_add_tier2_price'] ?? 0));
        Setting::set(OpticalRxConfig::PROGRESSIVE_NEGATIVE_SPH_SETTING, (float) ($data['progressive_negative_sph_price'] ?? 0));
        Setting::set(OpticalRxConfig::PROGRESSIVE_CYL_SETTING, (float) ($data['progressive_cyl_price'] ?? 0));

        Notification::make()->success()->title('Lens pricing saved')->send();
    }

    protected function getHeaderActions(): array
    {
        $currency = Setting::getDefaultCurrency();

        return collect(OpticalRxDiopterValue::groupOptions())
            ->filter(fn (string $label, string $group): bool => in_array($group, [
                OpticalRxDiopterValue::GROUP_SINGLE_SPH,
                OpticalRxDiopterValue::GROUP_SINGLE_CYL,
            ], true))
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
    protected function addValueRepeaterSchema(): array
    {
        return [
            Hidden::make('is_default')
                ->default(false),
            TextInput::make('value')
                ->label('ADD power')
                ->required()
                ->disabled(fn (Get $get): bool => (bool) $get('is_default'))
                ->dehydrated()
                ->helperText(fn (Get $get): ?string => $get('is_default')
                    ? 'Built-in value — cannot be removed.'
                    : 'Positive value up to +10.00, e.g. 4.25')
                ->placeholder('e.g. 4.25'),
        ];
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
            Section::make('Progressive — Lens Pricing')
                ->description('Progressive add-on is driven by ADD power. Flat price for +SPH/plano with ADD; stacked price when −SPH or CYL is present with ADD.')
                ->columns(2)
                ->schema([
                    TextInput::make('progressive_add_tier1_price')
                        ->label('ADD tier 1 (0 to +3.00)')
                        ->helperText('Plano/+SPH with ADD up to +3.00, or ADD-only progressive')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix($currency)
                        ->placeholder('3600.00'),

                    TextInput::make('progressive_add_tier2_price')
                        ->label('ADD tier 2 (above +3.00)')
                        ->helperText('ADD above +3.00, or +SPH and ADD both above +3.00')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix($currency)
                        ->placeholder('4500.00'),

                    TextInput::make('progressive_negative_sph_price')
                        ->label('Negative SPH price')
                        ->helperText('Any −SPH; stacks with ADD tier when ADD is present')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix($currency)
                        ->placeholder('2900.00'),

                    TextInput::make('progressive_cyl_price')
                        ->label('CYL price')
                        ->helperText('Any CYL; stacks with ADD tier when ADD is present')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->suffix($currency)
                        ->placeholder('2900.00'),
                ]),

            Section::make('Progressive — ADD values (POS dropdown)')
                ->description('ADD powers shown in POS for progressive prescriptions (+0.25 to +10.00 by default). Built-in values cannot be removed; add custom values as needed. Pricing still uses the ADD tiers above.')
                ->schema([
                    \Filament\Schemas\Components\Actions::make([
                        Action::make('open_progressive_add')
                            ->label('Edit ADD values')
                            ->icon('heroicon-o-pencil')
                            ->color('gray')
                            ->size('sm')
                            ->modalHeading('Configure: Progressive — ADD (near power)')
                            ->modalDescription('Values appear in the POS ADD column. Use two decimals, e.g. 4.25 or 10.00. Built-in values (greyed out) cannot be removed.')
                            ->modalWidth('2xl')
                            ->fillForm(fn (): array => ['rows' => OpticalRxConfig::addRowsForForm()])
                            ->form([
                                Repeater::make('rows')
                                    ->label(false)
                                    ->schema($this->addValueRepeaterSchema())
                                    ->defaultItems(0)
                                    ->addActionLabel('Add ADD value')
                                    ->deletable(fn (array $state): bool => ! ($state['is_default'] ?? false))
                                    ->reorderable(false)
                                    ->columnSpanFull(),
                            ])
                            ->action(function (array $data): void {
                                OpticalRxConfig::saveFromForm([
                                    OpticalRxDiopterValue::GROUP_PROGRESSIVE_ADD => $data['rows'],
                                ]);
                                Notification::make()->success()->title('ADD values saved')->send();
                            }),
                    ]),
                ]),

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

            Section::make('SPH & CYL Diopter Values (single vision)')
                ->description('Per-value add-on prices for single vision when only SPH or only CYL is present (not compound). Progressive pricing is configured above.')
                ->schema([
                    Grid::make(2)
                        ->schema(collect(OpticalRxDiopterValue::groupOptions())
                            ->filter(fn (string $label, string $group): bool => in_array($group, [
                                OpticalRxDiopterValue::GROUP_SINGLE_SPH,
                                OpticalRxDiopterValue::GROUP_SINGLE_CYL,
                            ], true))
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
                ->label('Save lens pricing')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }
}
