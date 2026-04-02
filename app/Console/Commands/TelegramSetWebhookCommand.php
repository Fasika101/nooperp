<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:bot:set-webhook';

    protected $description = 'Register Telegram bot webhook URL (uses APP_URL and integrations token)';

    public function handle(TelegramBotService $bots): int
    {
        $token = $bots->getBotToken();
        if (! $token) {
            $this->error('No bot token. Set TELEGRAM_BOT_TOKEN in .env or save it in Admin → Settings → Integrations.');

            return self::FAILURE;
        }

        $url = route('telegram.bot.webhook', absolute: true);
        $secret = $bots->getWebhookSecret();

        $params = [
            'url' => $url,
        ];
        if ($secret) {
            $params['secret_token'] = $secret;
        }

        $response = Http::timeout(30)->post(
            'https://api.telegram.org/bot'.$token.'/setWebhook',
            $params
        );

        $json = $response->json();
        if (! ($json['ok'] ?? false)) {
            $this->error($json['description'] ?? $response->body());

            return self::FAILURE;
        }

        $this->info('Webhook set to: '.$url);
        if ($secret) {
            $this->comment('Secret token is configured (header verification enabled).');
        }

        return self::SUCCESS;
    }
}
