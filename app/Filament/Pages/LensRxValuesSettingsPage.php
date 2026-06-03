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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
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

        $fill = [];
        foreach (OpticalRxDiopterValue::groups() as $group) {
            $fill[$group] = OpticalRxConfig::rowsForForm($group);
        }

        $this->form->fill($fill);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        OpticalRxConfig::saveFromForm($data);

        Notification::make()
            ->success()
            ->title('Lens RX values saved')
            ->send();

        $this->form->fill(collect(OpticalRxDiopterValue::groups())
            ->mapWithKeys(fn (string $group): array => [$group => OpticalRxConfig::rowsForForm($group)])
            ->all());
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $currency = Setting::getDefaultCurrency();

        return $schema
            ->components([
                Section::make()
                    ->description('Manage dropdown values and per-eye add-on prices for POS lens customization (SPH/CYL). Built-in values cannot be removed; you can add extra diopter steps (e.g. above +6.00). Unknown “—” in POS is always available and is not priced. Lens package prices are unchanged (Optical → Lens remarks).')
                    ->schema([
                        Tabs::make('Groups')
                            ->tabs(collect(OpticalRxDiopterValue::groupOptions())
                                ->map(fn (string $label, string $group): Tab => Tab::make($group)
                                    ->label($label)
                                    ->schema([
                                        Repeater::make($group)
                                            ->label('Values')
                                            ->schema($this->diopterRepeaterSchema($currency))
                                            ->defaultItems(0)
                                            ->addActionLabel('Add diopter value')
                                            ->deletable(fn (array $state): bool => ! ($state['is_default'] ?? false))
                                            ->reorderable(false)
                                            ->columnSpanFull(),
                                    ]))
                                ->values()
                                ->all()),
                    ]),
            ]);
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
                    ? 'Built-in value (cannot change or remove).'
                    : 'Use two decimals, e.g. 6.25 or -7.50')
                ->placeholder('e.g. 6.25'),
            TextInput::make('price')
                ->label('Add-on price (per eye)')
                ->numeric()
                ->minValue(0)
                ->step(0.01)
                ->default(0)
                ->required()
                ->suffix($currency),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
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
                ->label('Save')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }
}
