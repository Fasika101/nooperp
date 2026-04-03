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

    protected const FILE_API_BASE = 'https://api.telegram.org/file/bot';

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
    /**
     * @param  array<string, mixed>|null  $replyMarkup  Telegram ReplyKeyboardMarkup or similar (will be JSON-encoded)
     *
     * @throws \RuntimeException
     */
    public function sendTextToChat(
        TelegramBotChat $chat,
        string $text,
        ?array $replyMarkup = null,
        bool $removeKeyboard = false,
    ): TelegramBotMessage {
        $token = $this->getBotToken();
        if (! $token) {
            throw new \RuntimeException('Bot token is not configured.');
        }

        $payload = [
            'chat_id' => $chat->telegram_chat_id,
            'text' => $text,
        ];
        if ($removeKeyboard) {
            $payload['reply_markup'] = json_encode(['remove_keyboard' => true], JSON_THROW_ON_ERROR);
        } elseif ($replyMarkup !== null) {
            $payload['reply_markup'] = json_encode($replyMarkup, JSON_THROW_ON_ERROR);
        }

        $response = Http::timeout(30)->post(self::API_BASE.$token.'/sendMessage', $payload);

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
     * Send a photo or document to the chat and persist the outgoing message.
     *
     * @throws \RuntimeException
     */
    public function sendAttachmentToChat(TelegramBotChat $chat, string $absolutePath, string $originalFilename, ?string $caption): TelegramBotMessage
    {
        $token = $this->getBotToken();
        if (! $token) {
            throw new \RuntimeException('Bot token is not configured.');
        }

        if (! is_readable($absolutePath)) {
            throw new \RuntimeException('Upload file is not readable.');
        }

        $caption = $caption !== null ? trim($caption) : '';
        if (strlen($caption) > 1024) {
            throw new \RuntimeException('Caption must be 1024 characters or fewer.');
        }

        $mime = @mime_content_type($absolutePath) ?: 'application/octet-stream';
        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $isPhoto = str_starts_with((string) $mime, 'image/')
            && ! str_contains(strtolower((string) $mime), 'svg')
            && in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);

        $method = $isPhoto ? 'sendPhoto' : 'sendDocument';
        $field = $isPhoto ? 'photo' : 'document';

        $payload = [
            'chat_id' => $chat->telegram_chat_id,
        ];
        if ($caption !== '') {
            $payload['caption'] = $caption;
        }

        $stream = fopen($absolutePath, 'r');
        if ($stream === false) {
            throw new \RuntimeException('Could not open upload for reading.');
        }

        try {
            $response = Http::timeout(120)
                ->attach($field, $stream, $originalFilename)
                ->post(self::API_BASE.$token.'/'.$method, $payload);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $json = $response->json();
        if (! ($json['ok'] ?? false)) {
            $desc = $json['description'] ?? $response->body();

            throw new \RuntimeException('Telegram API error: '.$desc);
        }

        $result = $json['result'] ?? [];
        $messageId = (int) ($result['message_id'] ?? 0);
        $date = isset($result['date']) ? Carbon::createFromTimestamp($result['date']) : now();
        $dbText = $caption !== '' ? $caption : ('📎 '.$originalFilename);

        $msg = TelegramBotMessage::query()->updateOrCreate(
            [
                'telegram_bot_chat_id' => $chat->id,
                'telegram_message_id' => $messageId,
            ],
            [
                'direction' => TelegramBotMessage::DIRECTION_OUTGOING,
                'sent_at' => $date,
                'text' => $dbText,
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
     * Download file bytes from Telegram (server-side only — never expose token in browser).
     *
     * @return array{body: string, content_type: string}
     *
     * @throws \RuntimeException
     */
    public function fetchTelegramFile(string $fileId): array
    {
        $token = $this->getBotToken();
        if (! $token) {
            throw new \RuntimeException('Bot token is not configured.');
        }

        $info = Http::timeout(30)->post(self::API_BASE.$token.'/getFile', [
            'file_id' => $fileId,
        ])->json();

        if (! ($info['ok'] ?? false)) {
            throw new \RuntimeException($info['description'] ?? 'getFile failed.');
        }

        $filePath = $info['result']['file_path'] ?? null;
        if (! is_string($filePath) || $filePath === '') {
            throw new \RuntimeException('Invalid file path from Telegram.');
        }

        $fileUrl = self::FILE_API_BASE.$token.'/'.$filePath;
        $fileResponse = Http::timeout(120)->get($fileUrl);
        if (! $fileResponse->successful()) {
            throw new \RuntimeException('Failed to download file from Telegram.');
        }

        return [
            'body' => $fileResponse->body(),
            'content_type' => $fileResponse->header('Content-Type') ?: 'application/octet-stream',
        ];
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
            $text = $this->describeNonTextMessage($message);
        }

        $sentAt = isset($message['date'])
            ? Carbon::createFromTimestamp($message['date'])
            : now();

        $from = $message['from'] ?? null;

        $chat = TelegramBotChat::query()->firstOrNew(
            ['telegram_chat_id' => $chatId],
        );

        $chat->fill([
            'type' => (string) ($chatData['type'] ?? 'private'),
            'title' => $chatData['title'] ?? null,
            'username' => $chatData['username'] ?? null,
            'first_name' => $chatData['first_name'] ?? (is_array($from) ? ($from['first_name'] ?? null) : null),
            'last_name' => $chatData['last_name'] ?? (is_array($from) ? ($from['last_name'] ?? null) : null),
            'last_message_at' => $sentAt,
        ]);

        $chat->save();

        if ($isEdited) {
            $meta = $chat->meta ?? [];
            $meta['edited'] = true;
            $chat->update(['meta' => $meta]);
        }

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
            'last_incoming_message_at' => $sentAt,
        ]);

        $chat->refresh();

        try {
            app(TelegramBotCustomerOnboardingService::class)->handleIncoming($chat, $message, $text);
        } catch (Throwable $e) {
            Log::warning('Telegram customer onboarding: '.$e->getMessage(), ['exception' => $e]);
        }
    }

    /**
     * Human-readable label when there is no text/caption (media, stickers, etc.).
     *
     * @param  array<string, mixed>  $message
     */
    protected function describeNonTextMessage(array $message): string
    {
        if (! empty($message['document']) && is_array($message['document'])) {
            $name = $message['document']['file_name'] ?? null;

            return is_string($name) && $name !== '' ? '📎 '.$name : '📎 Document';
        }
        if (! empty($message['photo'])) {
            return '📷 Photo';
        }
        if (! empty($message['video'])) {
            return '🎬 Video';
        }
        if (! empty($message['voice'])) {
            return '🎤 Voice message';
        }
        if (! empty($message['audio'])) {
            return '🎵 Audio';
        }
        if (! empty($message['sticker'])) {
            return '🖼 Sticker';
        }
        if (! empty($message['video_note'])) {
            return '📹 Video note';
        }
        if (! empty($message['location'])) {
            return '📍 Location';
        }
        if (! empty($message['contact'])) {
            return '👤 Contact';
        }

        return '[non-text message]';
    }

    public function logWebhookError(Throwable $e): void
    {
        Log::error('Telegram bot webhook: '.$e->getMessage(), ['exception' => $e]);
    }
}
