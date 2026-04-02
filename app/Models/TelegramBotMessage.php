<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read array<string, mixed>|null $raw
 */
class TelegramBotMessage extends Model
{
    public const DIRECTION_INCOMING = 'incoming';

    public const DIRECTION_OUTGOING = 'outgoing';

    protected $fillable = [
        'telegram_bot_chat_id',
        'telegram_message_id',
        'direction',
        'sent_at',
        'text',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'telegram_message_id' => 'integer',
            'sent_at' => 'datetime',
            'raw' => 'array',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegramBotChat::class, 'telegram_bot_chat_id');
    }

    /**
     * Largest photo file_id when message has Telegram `photo` array.
     */
    public function telegramPhotoFileId(): ?string
    {
        $raw = $this->raw;
        if (! is_array($raw) || empty($raw['photo']) || ! is_array($raw['photo'])) {
            return null;
        }

        $last = end($raw['photo']);

        return isset($last['file_id']) && is_string($last['file_id']) ? $last['file_id'] : null;
    }

    public function telegramDocumentFileId(): ?string
    {
        $raw = $this->raw;
        if (! is_array($raw) || empty($raw['document']) || ! is_array($raw['document'])) {
            return null;
        }

        $id = $raw['document']['file_id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function telegramVideoFileId(): ?string
    {
        $raw = $this->raw;
        if (! is_array($raw) || empty($raw['video']) || ! is_array($raw['video'])) {
            return null;
        }

        $id = $raw['video']['file_id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function telegramStickerFileId(): ?string
    {
        $raw = $this->raw;
        if (! is_array($raw) || empty($raw['sticker']) || ! is_array($raw['sticker'])) {
            return null;
        }

        $id = $raw['sticker']['file_id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function telegramVoiceFileId(): ?string
    {
        $raw = $this->raw;
        if (! is_array($raw) || empty($raw['voice']) || ! is_array($raw['voice'])) {
            return null;
        }

        $id = $raw['voice']['file_id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function telegramAudioFileId(): ?string
    {
        $raw = $this->raw;
        if (! is_array($raw) || empty($raw['audio']) || ! is_array($raw['audio'])) {
            return null;
        }

        $id = $raw['audio']['file_id'] ?? null;

        return is_string($id) ? $id : null;
    }

    /**
     * One file_id to fetch via getFile (first match in display priority).
     */
    public function telegramFileId(): ?string
    {
        return $this->telegramPhotoFileId()
            ?? $this->telegramVideoFileId()
            ?? $this->telegramStickerFileId()
            ?? $this->telegramDocumentFileId()
            ?? $this->telegramVoiceFileId()
            ?? $this->telegramAudioFileId();
    }

    public function hasTelegramAttachment(): bool
    {
        return $this->telegramFileId() !== null;
    }

    /**
     * Safe to embed in <img> via authenticated proxy (raster images + webp stickers).
     */
    public function telegramAttachmentDisplayableAsImage(): bool
    {
        if ($this->telegramPhotoFileId() !== null || $this->telegramStickerFileId() !== null) {
            return true;
        }

        $raw = $this->raw;
        if (! is_array($raw) || empty($raw['document']) || ! is_array($raw['document'])) {
            return false;
        }

        $mime = $raw['document']['mime_type'] ?? '';

        return is_string($mime) && str_starts_with($mime, 'image/');
    }

    public function telegramAttachmentDownloadName(): ?string
    {
        $raw = $this->raw;
        if (! is_array($raw)) {
            return null;
        }

        if (! empty($raw['document']['file_name']) && is_string($raw['document']['file_name'])) {
            return $raw['document']['file_name'];
        }

        if ($this->telegramPhotoFileId() !== null) {
            return 'photo.jpg';
        }

        if (! empty($raw['video']['file_name']) && is_string($raw['video']['file_name'])) {
            return $raw['video']['file_name'];
        }

        return null;
    }
}
