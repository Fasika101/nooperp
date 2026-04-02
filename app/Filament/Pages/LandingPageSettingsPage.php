<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\Facades\Storage;

class LandingPageSettingsPage extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Landing page & SEO';

    protected static ?string $title = 'Landing page & SEO';

    protected static ?int $navigationSort = 9;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'public_brand_name' => Setting::get('public_brand_name'),
            'public_meta_title' => Setting::get('public_meta_title'),
            'public_meta_description' => Setting::get('public_meta_description'),
            'public_hero_title' => Setting::get('public_hero_title'),
            'public_hero_lead' => Setting::get('public_hero_lead'),
            'public_feature_1_title' => Setting::get('public_feature_1_title'),
            'public_feature_1_text' => Setting::get('public_feature_1_text'),
            'public_feature_2_title' => Setting::get('public_feature_2_title'),
            'public_feature_2_text' => Setting::get('public_feature_2_text'),
            'public_primary_button_label' => Setting::get('public_primary_button_label'),
            'public_theme_color' => Setting::get('public_theme_color') ?: '#FDFDFC',
            'logo_upload' => Setting::get('public_logo_storage_path'),
            'favicon_upload' => Setting::get('public_favicon_storage_path'),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $this->syncPublicDiskUpload(
            $this->normalizeUploadPath($data['logo_upload'] ?? null),
            'public_logo_storage_path'
        );

        $this->syncPublicDiskUpload(
            $this->normalizeUploadPath($data['favicon_upload'] ?? null),
            'public_favicon_storage_path'
        );

        $trimOrNull = fn (?string $v): ?string => (($t = is_string($v) ? trim($v) : '') !== '') ? $t : null;

        Setting::set('public_brand_name', $trimOrNull($data['public_brand_name'] ?? null));
        Setting::set('public_meta_title', $trimOrNull($data['public_meta_title'] ?? null));
        Setting::set('public_meta_description', $trimOrNull($data['public_meta_description'] ?? null));
        Setting::set('public_hero_title', $trimOrNull($data['public_hero_title'] ?? null));
        Setting::set('public_hero_lead', $trimOrNull($data['public_hero_lead'] ?? null));
        Setting::set('public_feature_1_title', $trimOrNull($data['public_feature_1_title'] ?? null));
        Setting::set('public_feature_1_text', $trimOrNull($data['public_feature_1_text'] ?? null));
        Setting::set('public_feature_2_title', $trimOrNull($data['public_feature_2_title'] ?? null));
        Setting::set('public_feature_2_text', $trimOrNull($data['public_feature_2_text'] ?? null));
        Setting::set('public_primary_button_label', $trimOrNull($data['public_primary_button_label'] ?? null));
        Setting::set('public_theme_color', $trimOrNull($data['public_theme_color'] ?? null));

        $this->form->fill([
            'public_brand_name' => Setting::get('public_brand_name'),
            'public_meta_title' => Setting::get('public_meta_title'),
            'public_meta_description' => Setting::get('public_meta_description'),
            'public_hero_title' => Setting::get('public_hero_title'),
            'public_hero_lead' => Setting::get('public_hero_lead'),
            'public_feature_1_title' => Setting::get('public_feature_1_title'),
            'public_feature_1_text' => Setting::get('public_feature_1_text'),
            'public_feature_2_title' => Setting::get('public_feature_2_title'),
            'public_feature_2_text' => Setting::get('public_feature_2_text'),
            'public_primary_button_label' => Setting::get('public_primary_button_label'),
            'public_theme_color' => Setting::get('public_theme_color') ?: '#FDFDFC',
            'logo_upload' => Setting::get('public_logo_storage_path'),
            'favicon_upload' => Setting::get('public_favicon_storage_path'),
        ]);

        Notification::make()
            ->success()
            ->title(__('Landing page saved'))
            ->send();
    }

    private function syncPublicDiskUpload(?string $newPath, string $settingKey): void
    {
        $disk = Storage::disk('public');
        $old = Setting::get($settingKey);
        $old = is_string($old) ? $old : null;
        $new = ($newPath !== null && $newPath !== '') ? $newPath : null;

        if ($old === $new) {
            return;
        }

        if ($old !== null && $old !== '' && $disk->exists($old)) {
            $disk->delete($old);
        }

        Setting::set($settingKey, $new);
    }

    private function normalizeUploadPath(mixed $state): ?string
    {
        if (is_string($state) && $state !== '') {
            return $state;
        }

        if (is_array($state) && $state !== []) {
            $first = reset($state);

            return is_string($first) && $first !== '' ? $first : null;
        }

        return null;
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
                Section::make(__('Branding & assets'))
                    ->description(__('Logo and favicon are stored in public storage (/storage). Ensure `php artisan storage:link` has been run on the server. For best link previews (Telegram, etc.), use a PNG or JPG logo; SVG is fine for the site but may not appear in all social previews.'))
                    ->schema([
                        FileUpload::make('logo_upload')
                            ->label(__('Main logo'))
                            ->helperText(__('Shown on the home page hero. Raster formats work everywhere; SVG is OK for the page only.'))
                            ->image()
                            ->disk('public')
                            ->directory('site-branding')
                            ->visibility('public')
                            ->maxSize(3072)
                            ->nullable()
                            ->imagePreviewHeight('120')
                            ->columnSpanFull(),
                        FileUpload::make('favicon_upload')
                            ->label(__('Favicon'))
                            ->helperText(__('ICO or PNG, roughly square. Shown in browser tabs and bookmarks.'))
                            ->disk('public')
                            ->directory('site-branding')
                            ->visibility('public')
                            ->maxSize(512)
                            ->nullable()
                            ->acceptedFileTypes(['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/jpeg'])
                            ->columnSpanFull(),
                        ColorPicker::make('public_theme_color')
                            ->label(__('Theme color (meta)'))
                            ->helperText(__('Used for `theme-color` in meta tags (browser UI tint).'))
                            ->default('#FDFDFC'),
                        TextInput::make('public_brand_name')
                            ->label(__('Short brand name'))
                            ->helperText(__('Used for Open Graph `site_name`, logo alt text, and defaults. Leave blank to use the app name from config.'))
                            ->maxLength(120),
                    ])
                    ->columns(1),
                Section::make(__('SEO & meta tags'))
                    ->description(fn (): string => __('Used on the public home page, Telegram/WhatsApp link previews, and search snippets. Live site: :url', ['url' => url('/')]))
                    ->schema([
                        TextInput::make('public_meta_title')
                            ->label(__('Page title'))
                            ->helperText(__('HTML `<title>` and og:title. Leave blank for: brand name — ERP & inventory.'))
                            ->maxLength(120),
                        Textarea::make('public_meta_description')
                            ->label(__('Meta description'))
                            ->helperText(__('Leave blank for the built-in default paragraph.'))
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->columns(1),
                Section::make(__('Home page content'))
                    ->description(__('Text shown on the main marketing block (left column on large screens).'))
                    ->schema([
                        TextInput::make('public_hero_title')
                            ->label(__('Main heading'))
                            ->maxLength(180),
                        Textarea::make('public_hero_lead')
                            ->label(__('Intro paragraph'))
                            ->rows(3)
                            ->maxLength(600),
                        TextInput::make('public_feature_1_title')
                            ->label(__('Feature 1 — title'))
                            ->maxLength(120),
                        Textarea::make('public_feature_1_text')
                            ->label(__('Feature 1 — description'))
                            ->rows(2)
                            ->maxLength(400),
                        TextInput::make('public_feature_2_title')
                            ->label(__('Feature 2 — title'))
                            ->maxLength(120),
                        Textarea::make('public_feature_2_text')
                            ->label(__('Feature 2 — description'))
                            ->rows(2)
                            ->maxLength(400),
                        TextInput::make('public_primary_button_label')
                            ->label(__('Primary button label'))
                            ->helperText(__('Usually “Log in now”. Links to the admin login.'))
                            ->maxLength(80),
                    ])
                    ->columns(1),
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

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('Save'))
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }
}
