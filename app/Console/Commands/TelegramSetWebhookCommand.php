<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TelegramSetWebhookCommand extends Command
{
    protected $signature = 'telegram:bot:set-webhook';

    protected $description = 'Register Telegram bot webhook URL (uses APP_URL and integrations token)';

    public function handle(): int
    {
        $token = Setting::getEncrypted('integrations_telegram_bot_token');
        if (! $token) {
            $this->error('No bot token saved. Set it in Admin → Settings → Integrations.');

            return self::FAILURE;
        }

        $url = route('telegram.bot.webhook', absolute: true);
        $secret = Setting::getEncrypted('integrations_telegram_webhook_secret');

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
