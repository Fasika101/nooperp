<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\TelegramBotChat;
use App\Models\TelegramBotMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramBotService
{
    protected const API_BASE = 'https://api.telegram.org/bot';

    public function getBotToken(): ?string
    {
        $fromDb = Setting::getEncrypted('integrations_telegram_bot_token');
        if ($fromDb !== null && $fromDb !== '') {
            return $fromDb;
        }

        $fromEnv = config('integrations.telegram_bot_token');

        return is_string($fromEnv) && trim($fromEnv) !== '' ? trim($fromEnv) : null;
    }

    public function getWebhookSecret(): ?string
    {
        $fromDb = Setting::getEncrypted('integrations_telegram_webhook_secret');
        if ($fromDb !== null && $fromDb !== '') {
            return $fromDb;
        }

        $fromEnv = config('integrations.telegram_webhook_secret');

        return is_string($fromEnv) && trim($fromEnv) !== '' ? trim($fromEnv) : null;
    }

    public function hasBotToken(): bool
    {
        return $this->getBotToken() !== null && $this->getBotToken() !== '';
    }

    /**
     * @return array{ok: bool, result?: array, description?: string}
     */
    public function getMe(): array
    {
        $token = $this->getBotToken();
        if (! $token) {
            return ['ok' => false, 'description' => 'Bot token is not configured.'];
        }

        $response = Http::timeout(15)->get(self::API_BASE.$token.'/getMe');

        return $response->json() ?? ['ok' => false, 'description' => 'Invalid response from Telegram.'];
    }

    /**
     * Send a text message and persist it as outgoing.
     *
     * @throws \RuntimeException
     */
    public function sendTextToChat(TelegramBotChat $chat, string $text): TelegramBotMessage
    {
        $token = $this->getBotToken();
        if (! $token) {
            throw new \RuntimeException('Bot token is not configured.');
        }

        $response = Http::timeout(30)->post(self::API_BASE.$token.'/sendMessage', [
            'chat_id' => $chat->telegram_chat_id,
            'text' => $text,
        ]);

        $json = $response->json();
        if (! ($json['ok'] ?? false)) {
            $desc = $json['description'] ?? $response->body();

            throw new \RuntimeException('Telegram API error: '.$desc);
        }

        $result = $json['result'] ?? [];
        $messageId = (int) ($result['message_id'] ?? 0);
        $date = isset($result['date']) ? Carbon::createFromTimestamp($result['date']) : now();

        $msg = TelegramBotMessage::query()->updateOrCreate(
            [
                'telegram_bot_chat_id' => $chat->id,
                'telegram_message_id' => $messageId,
            ],
            [
                'direction' => TelegramBotMessage::DIRECTION_OUTGOING,
                'sent_at' => $date,
                'text' => $text,
                'raw' => $result,
            ]
        );

        $chat->update([
            'last_message_at' => $date,
            'message_count' => $chat->messages()->count(),
        ]);

        return $msg;
    }

    /**
     * Handle a single update object from the Bot API webhook.
     */
    public function processWebhookUpdate(array $update): void
    {
        if (isset($update['message'])) {
            $this->storeIncomingMessage($update['message']);

            return;
        }

        if (isset($update['edited_message'])) {
            $this->storeIncomingMessage($update['edited_message'], isEdited: true);

            return;
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function storeIncomingMessage(array $message, bool $isEdited = false): void
    {
        $chatData = $message['chat'] ?? [];
        $chatId = (int) ($chatData['id'] ?? 0);
        if ($chatId === 0) {
            return;
        }

        $messageId = (int) ($message['message_id'] ?? 0);
        if ($messageId === 0) {
            return;
        }

        $text = $message['text'] ?? null;
        if ($text === null && isset($message['caption'])) {
            $text = (string) $message['caption'];
        }
        if ($text === null) {
            $text = '[non-text message]';
        }

        $sentAt = isset($message['date'])
            ? Carbon::createFromTimestamp($message['date'])
            : now();

        $from = $message['from'] ?? null;

        $chat = TelegramBotChat::query()->updateOrCreate(
            ['telegram_chat_id' => $chatId],
            [
                'type' => (string) ($chatData['type'] ?? 'private'),
                'title' => $chatData['title'] ?? null,
                'username' => $chatData['username'] ?? null,
                'first_name' => $chatData['first_name'] ?? ($from['first_name'] ?? null),
                'last_name' => $chatData['last_name'] ?? ($from['last_name'] ?? null),
                'last_message_at' => $sentAt,
                'meta' => $isEdited ? ['edited' => true] : null,
            ]
        );

        TelegramBotMessage::query()->updateOrCreate(
            [
                'telegram_bot_chat_id' => $chat->id,
                'telegram_message_id' => $messageId,
            ],
            [
                'direction' => TelegramBotMessage::DIRECTION_INCOMING,
                'sent_at' => $sentAt,
                'text' => is_string($text) ? $text : json_encode($text),
                'raw' => $message,
            ]
        );

        $chat->update([
            'message_count' => $chat->messages()->count(),
            'last_message_at' => $sentAt,
        ]);

        $this->maybeSendWelcomeReply($chat, is_string($text) ? $text : null);
    }

    /**
     * Telegram shows nothing unless the bot calls sendMessage. Reply to /start so operators can confirm the hook works.
     */
    protected function maybeSendWelcomeReply(TelegramBotChat $chat, ?string $text): void
    {
        if (! is_string($text) || $text === '' || $text === '[non-text message]') {
            return;
        }

        if (! str_starts_with(trim($text), '/start')) {
            return;
        }

        $msg = config('integrations.telegram_welcome_message');
        if (! is_string($msg) || trim($msg) === '') {
            return;
        }

        try {
            $this->sendTextToChat($chat, $msg);
        } catch (Throwable $e) {
            Log::warning('Telegram bot welcome reply failed: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    public function logWebhookError(Throwable $e): void
    {
        Log::error('Telegram bot webhook: '.$e->getMessage(), ['exception' => $e]);
    }
}
