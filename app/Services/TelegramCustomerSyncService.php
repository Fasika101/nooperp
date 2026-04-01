<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\TelegramChat;
use App\Models\TelegramMessage;

class TelegramCustomerSyncService
{
    /**
     * Upsert customers from 1:1 Telegram chats: display name from chat; phone from imported
     * Telegram user (meta), sender_phone on messages, else regex on message text.
     *
     * @return array{created: int, updated: int, skipped: int}
     */
    public function syncUserChatsToCustomers(): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;

        $chats = TelegramChat::query()
            ->where('type', 'user')
            ->orderBy('id')
            ->get();

        foreach ($chats as $chat) {
            $name = $this->resolveCustomerName($chat);
            if ($name === '') {
                $skipped++;

                continue;
            }

            $phone = $this->findBestPhoneForChat($chat);

            $customer = Customer::query()->firstOrNew([
                'telegram_peer_id' => $chat->telegram_peer_id,
            ]);

            $wasNew = ! $customer->exists;

            $customer->fill([
                'name' => $name,
                'phone' => $phone,
            ]);

            if ($wasNew) {
                $customer->save();
                $created++;
            } elseif ($customer->isDirty()) {
                $customer->save();
                $updated++;
            }
        }

        return compact('created', 'updated', 'skipped');
    }

    protected function resolveCustomerName(TelegramChat $chat): string
    {
        $title = trim((string) ($chat->title ?? ''));
        if ($title !== '') {
            return $title;
        }

        $username = trim((string) ($chat->username ?? ''));
        if ($username !== '') {
            return '@'.$username;
        }

        return '';
    }

    protected function findBestPhoneForChat(TelegramChat $chat): ?string
    {
        $meta = $chat->meta;
        if (is_array($meta) && isset($meta['phone'])) {
            $n = $this->normalizePhone((string) $meta['phone']);
            if ($n !== '') {
                return $n;
            }
        }

        $messages = TelegramMessage::query()
            ->where('telegram_chat_id', $chat->id)
            ->orderByDesc('sent_at')
            ->get();

        foreach ($messages as $message) {
            $raw = $message->raw;
            if (is_array($raw) && ! empty($raw['sender_phone'])) {
                $n = $this->normalizePhone((string) $raw['sender_phone']);
                if ($n !== '') {
                    return $n;
                }
            }
        }

        return $this->findBestPhoneFromMessageText($chat);
    }

    protected function findBestPhoneFromMessageText(TelegramChat $chat): ?string
    {
        $candidates = [];

        $messages = TelegramMessage::query()
            ->where('telegram_chat_id', $chat->id)
            ->whereNotNull('text')
            ->orderByDesc('sent_at')
            ->get();

        foreach ($messages as $message) {
            $text = (string) $message->text;
            foreach ($this->extractPhonesFromText($text) as $normalized) {
                if ($normalized === '') {
                    continue;
                }
                $weight = $message->is_outgoing ? 1 : 3;
                $candidates[$normalized] = ($candidates[$normalized] ?? 0) + $weight;
            }
        }

        if ($candidates === []) {
            return null;
        }

        arsort($candidates);

        return array_key_first($candidates);
    }

    /**
     * @return list<string>
     */
    public function extractPhonesFromText(string $text): array
    {
        $patterns = config('telegram_crm.phone_patterns', []);
        if (! is_array($patterns) || $patterns === []) {
            return [];
        }

        $found = [];
        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }
            if (@preg_match_all($pattern, $text, $matches) !== false && isset($matches[0])) {
                foreach ($matches[0] as $raw) {
                    $n = $this->normalizePhone((string) $raw);
                    if ($n !== '') {
                        $found[] = $n;
                    }
                }
            }
        }

        return array_values(array_unique($found));
    }

    public function normalizePhone(string $raw): string
    {
        $t = trim($raw);
        if ($t === '') {
            return '';
        }

        if (str_starts_with($t, '+')) {
            $digits = preg_replace('/\D+/', '', substr($t, 1));

            return $digits !== '' ? '+'.$digits : '';
        }

        return (string) preg_replace('/\D+/', '', $t);
    }
}
