<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\TelegramBotService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
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

class IntegrationsPage extends Page
{
    use HasPageShield;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Integrations';

    protected static ?string $title = 'Integrations';

    protected static ?int $navigationSort = 8;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'telegram_bot_token' => '',
            'telegram_webhook_secret' => '',
            'webhook_url_display' => route('telegram.bot.webhook', absolute: true),
        ]);
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
                Section::make('Telegram bot')
                    ->description(
                        'Create a bot with @BotFather, then paste the token here. Production requires HTTPS; on localhost use ngrok (or similar) so Telegram can reach your webhook. After saving the token, run: php artisan telegram:bot:set-webhook'
                    )
                    ->schema([
                        TextInput::make('telegram_bot_token')
                            ->label('Bot token')
                            ->password()
                            ->revealable()
                            ->helperText(
                                Setting::hasEncrypted('integrations_telegram_bot_token')
                                    ? 'A token is already saved. Enter a new value only if you want to replace it.'
                                    : 'Example format: 123456789:AAH…'
                            ),
                        TextInput::make('telegram_webhook_secret')
                            ->label('Webhook secret (optional)')
                            ->password()
                            ->revealable()
                            ->helperText(
                                'If set, Telegram will send header X-Telegram-Bot-Api-Secret-Token and this app will verify it. Recommended for production.'
                            ),
                        TextInput::make('webhook_url_display')
                            ->label('Webhook URL')
                            ->disabled()
                            ->dehydrated(false),
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
                ->label('Save integrations')
                ->submit('save')
                ->keyBindings(['mod+s']),
            Action::make('test_telegram')
                ->label('Test Telegram bot')
                ->color('gray')
                ->action('testTelegramBot'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $token = trim((string) ($data['telegram_bot_token'] ?? ''));
        if ($token !== '') {
            Setting::setEncrypted('integrations_telegram_bot_token', $token);
        }

        $whSecret = trim((string) ($data['telegram_webhook_secret'] ?? ''));
        if ($whSecret !== '') {
            Setting::setEncrypted('integrations_telegram_webhook_secret', $whSecret);
        }

        $this->form->fill([
            'telegram_bot_token' => '',
            'telegram_webhook_secret' => '',
            'webhook_url_display' => route('telegram.bot.webhook', absolute: true),
        ]);

        Notification::make()
            ->success()
            ->title('Integrations saved')
            ->send();
    }

    public function testTelegramBot(): void
    {
        $json = app(TelegramBotService::class)->getMe();
        if ($json['ok'] ?? false) {
            $username = $json['result']['username'] ?? '?';
            Notification::make()
                ->success()
                ->title('Telegram OK')
                ->body('Connected as @'.$username)
                ->send();

            return;
        }

        Notification::make()
            ->danger()
            ->title('Telegram connection failed')
            ->body($json['description'] ?? 'Unknown error')
            ->send();
    }
}
