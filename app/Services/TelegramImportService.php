<?php

namespace App\Services;

use App\Models\TelegramChat;
use App\Models\TelegramMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class TelegramImportService
{
    /**
     * Import from JSON file produced by scripts/telegram_export/export.py
     *
     * @param  array<string, mixed>  $payload
     */
    public function importFromArray(array $payload, bool $replaceAll = true): array
    {
        $chats = $payload['chats'] ?? [];
        if (! is_array($chats)) {
            throw new \InvalidArgumentException('Invalid export: missing "chats" array.');
        }

        $imported = DB::transaction(function () use ($chats, $replaceAll): array {
            if ($replaceAll) {
                TelegramMessage::query()->delete();
                TelegramChat::query()->delete();
            }

            $chatCount = 0;
            $msgCount = 0;

            foreach ($chats as $chatBlock) {
                if (! is_array($chatBlock)) {
                    continue;
                }

                $peerId = (string) ($chatBlock['peer_id'] ?? '');
                if ($peerId === '') {
                    continue;
                }

                $messages = $chatBlock['messages'] ?? [];
                if (! is_array($messages)) {
                    $messages = [];
                }

                $lastAt = null;
                foreach ($messages as $m) {
                    if (is_array($m) && isset($m['date'])) {
                        try {
                            $d = Carbon::parse($m['date']);
                            if ($lastAt === null || $d->gt($lastAt)) {
                                $lastAt = $d;
                            }
                        } catch (Throwable) {
                            //
                        }
                    }
                }

                $meta = is_array($chatBlock['meta'] ?? null) ? $chatBlock['meta'] : [];
                if (isset($chatBlock['phone']) && $chatBlock['phone'] !== null && (string) $chatBlock['phone'] !== '') {
                    $meta['phone'] = (string) $chatBlock['phone'];
                }

                $chat = TelegramChat::query()->updateOrCreate(
                    ['telegram_peer_id' => $peerId],
                    [
                        'type' => (string) ($chatBlock['type'] ?? 'user'),
                        'title' => isset($chatBlock['title']) ? (string) $chatBlock['title'] : null,
                        'username' => isset($chatBlock['username']) ? (string) $chatBlock['username'] : null,
                        'last_message_at' => $lastAt,
                        'message_count' => count($messages),
                        'imported_at' => now(),
                        'meta' => $meta !== [] ? $meta : null,
                    ]
                );

                $chatCount++;

                foreach ($messages as $row) {
                    if (! is_array($row)) {
                        continue;
                    }

                    $mid = (int) ($row['id'] ?? 0);
                    if ($mid < 1) {
                        continue;
                    }

                    $sentAt = null;
                    if (! empty($row['date'])) {
                        try {
                            $sentAt = Carbon::parse($row['date']);
                        } catch (Throwable) {
                            $sentAt = null;
                        }
                    }

                    $raw = isset($row['raw']) && is_array($row['raw']) ? $row['raw'] : [];
                    if (isset($row['sender_phone']) && $row['sender_phone'] !== null && (string) $row['sender_phone'] !== '') {
                        $raw['sender_phone'] = (string) $row['sender_phone'];
                    }

                    TelegramMessage::query()->updateOrCreate(
                        [
                            'telegram_chat_id' => $chat->id,
                            'telegram_message_id' => $mid,
                        ],
                        [
                            'sent_at' => $sentAt,
                            'is_outgoing' => (bool) ($row['out'] ?? false),
                            'sender_peer_id' => isset($row['sender_peer_id']) ? (string) $row['sender_peer_id'] : null,
                            'sender_name' => isset($row['sender_name']) ? (string) $row['sender_name'] : null,
                            'text' => isset($row['text']) ? (string) $row['text'] : null,
                            'raw' => $raw !== [] ? $raw : null,
                        ]
                    );
                    $msgCount++;
                }
            }

            return ['chats' => $chatCount, 'messages' => $msgCount];
        });

        return $imported;
    }
}
