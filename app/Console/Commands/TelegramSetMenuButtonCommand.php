<?php

namespace App\Console\Commands;

use App\Services\TelegramBotService;
use Illuminate\Console\Command;

class TelegramSetMenuButtonCommand extends Command
{
    protected $signature = 'telegram:bot:set-menu-button';

    protected $description = 'Set the Telegram bot Menu Button to open the /shop Mini App';

    public function handle(TelegramBotService $bots): int
    {
        if (! $bots->hasBotToken()) {
            $this->error('No bot token. Set TELEGRAM_BOT_TOKEN in .env or Admin → Settings → Integrations.');

            return self::FAILURE;
        }

        $shopUrl = $bots->getShopUrl();
        if (! str_starts_with($shopUrl, 'https://')) {
            $this->warn('Shop URL is not HTTPS: '.$shopUrl);
            $this->comment('Telegram Mini Apps require HTTPS. Set APP_URL or TELEGRAM_SHOP_URL to your public https domain.');
        }

        $result = $bots->setShopMenuButton();
        if (! ($result['ok'] ?? false)) {
            $this->error($result['description'] ?? 'Failed to set menu button.');

            return self::FAILURE;
        }

        $this->info('Menu button set to open: '.$shopUrl);
        $this->comment('In Telegram, open your bot — you should see "Shop" (or your label) next to the message field.');
        $this->newLine();
        $this->line('Channel tips:');
        $this->line('  1. Add the bot as an admin of your channel (Post Messages).');
        $this->line('  2. Post a channel message linking to your bot, e.g. https://t.me/YourBotUsername');
        $this->line('  3. Shoppers open the bot → tap Shop / Open shop to browse.');

        return self::SUCCESS;
    }
}
