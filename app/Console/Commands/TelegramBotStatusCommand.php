<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramBotStatusCommand extends Command
{
    protected $signature = 'telegram:bot:status';

    protected $description = 'Show Telegram bot token validity (getMe) and current webhook (getWebhookInfo)';

    public function handle(TelegramBotService $bots): int
    {
        if (! $bots->hasBotToken()) {
            $this->error('No bot token. Set TELEGRAM_BOT_TOKEN in .env or save the token in Admin → Settings → Integrations.');

            return self::FAILURE;
        }

        $token = $bots->getBotToken();
        $me = $bots->getMe();
        if (! ($me['ok'] ?? false)) {
            $this->error('getMe failed: '.($me['description'] ?? 'unknown'));

            return self::FAILURE;
        }

        $this->info('Bot: @'.($me['result']['username'] ?? '?').' (id '.($me['result']['id'] ?? '?').')');

        $whResponse = Http::timeout(30)->get(
            'https://api.telegram.org/bot'.$token.'/getWebhookInfo'
        );
        $wh = $whResponse->json();
        if (! ($wh['ok'] ?? false)) {
            $this->warn('getWebhookInfo failed: '.($wh['description'] ?? $whResponse->body()));

            return self::FAILURE;
        }

        $info = $wh['result'] ?? [];
        $this->newLine();
        $this->line('Webhook URL: '.($info['url'] ?? '(not set)'));
        if (! empty($info['last_error_message'])) {
            $this->error('Last error: '.($info['last_error_message'] ?? ''));
            $this->line('Last error date: '.($info['last_error_date'] ?? ''));
        }
        if (! empty($info['pending_update_count'])) {
            $this->warn('Pending updates: '.(int) $info['pending_update_count']);
        }

        $expectedUrl = route('telegram.bot.webhook', absolute: true);
        $actualUrl = (string) ($info['url'] ?? '');
        if ($actualUrl !== $expectedUrl) {
            $this->newLine();
            $this->warn('APP_URL webhook does not match Laravel route. Expected something like:');
            $this->line($expectedUrl);
            $this->comment('Run: php artisan telegram:bot:set-webhook');
        }

        return self::SUCCESS;
    }
}
