<?php

namespace App\Filament\Pages;

use App\Models\ProductOption;
use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;

class SettingsPage extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Business Settings';

    protected static ?string $title = 'Settings';

    protected static ?int $navigationSort = 10;

    public ?array $data = [];

    public function mount(): void
    {
        $fill = [
            'default_currency' => Setting::get('default_currency', 'ETB'),
            'business_phone' => Setting::get('business_phone'),
            'business_email' => Setting::get('business_email'),
            'business_tin' => Setting::get('business_tin'),
        ];

        foreach (Setting::getProductOptionFieldsEnabled() as $type => $enabled) {
            $fill['product_option_enabled_'.$type] = $enabled;
        }

        $this->form->fill($fill);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        Setting::set('default_currency', $data['default_currency'] ?? 'ETB');
        Setting::set('business_phone', $data['business_phone'] ?: null);
        Setting::set('business_email', $data['business_email'] ?: null);
        Setting::set('business_tin', $data['business_tin'] ?: null);

        $optionFlags = [];
        foreach (array_keys(ProductOption::getTypeOptions()) as $type) {
            $optionFlags[$type] = (bool) ($data['product_option_enabled_'.$type] ?? false);
        }
        Setting::setProductOptionFieldsEnabled($optionFlags);

        Notification::make()
            ->success()
            ->title('Settings saved successfully')
            ->send();
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Business Information')
                    ->description('This information appears on receipts.')
                    ->schema([
                        Select::make('default_currency')
                            ->label('Default Currency')
                            ->options([
                                'ETB' => 'ETB - Ethiopian Birr',
                                'USD' => 'USD - US Dollar',
                                'EUR' => 'EUR - Euro',
                                'GBP' => 'GBP - British Pound',
                            ])
                            ->default('ETB')
                            ->required(),
                        TextInput::make('business_phone')
                            ->label('Phone Number')
                            ->tel()
                            ->placeholder('+1 234 567 8900'),
                        TextInput::make('business_email')
                            ->label('Email')
                            ->email()
                            ->placeholder('business@example.com'),
                        TextInput::make('business_tin')
                            ->label('TIN (Tax ID)')
                            ->placeholder('Tax identification number'),
                    ])
                    ->columns(1),
                Section::make('Product attributes')
                    ->description('Turn off attributes you do not use. Hidden fields are omitted from product forms; matching columns and filters are hidden on the product list.')
                    ->schema(collect(ProductOption::getTypeOptions())
                        ->map(fn (string $label, string $type): Toggle => Toggle::make('product_option_enabled_'.$type)
                            ->label($label)
                            ->default(true))
                        ->values()
                        ->all())
                    ->columns(2),
            ]);
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
