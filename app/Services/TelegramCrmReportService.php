<?php

namespace App\Services;

use App\Models\TelegramMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TelegramCrmReportService
{
    /**
     * @return array<string, array{count: int, samples: Collection<int, TelegramMessage>}>
     */
    public function keywordCounts(int $sampleLimit = 5): array
    {
        $keywords = config('telegram_crm.product_keywords', []);
        $out = [];

        foreach ($keywords as $kw) {
            $kw = trim((string) $kw);
            if ($kw === '') {
                continue;
            }

            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($kw)).'%';

            $q = TelegramMessage::query()
                ->whereNotNull('text')
                ->whereRaw('LOWER(text) LIKE ?', [$like]);

            $count = (clone $q)->count();
            $samples = (clone $q)->orderByDesc('sent_at')->limit($sampleLimit)->get();

            $out[$kw] = [
                'count' => $count,
                'samples' => $samples,
            ];
        }

        return $out;
    }

    /**
     * Messages that may contain location/address hints (keyword substring match).
     *
     * @return Collection<int, TelegramMessage>
     */
    public function addressHintMessages(int $limit = 50): Collection
    {
        $keywords = config('telegram_crm.address_keywords', []);
        $query = TelegramMessage::query()->whereNotNull('text');

        $query->where(function ($q) use ($keywords): void {
            foreach ($keywords as $word) {
                if (! is_string($word) || trim($word) === '') {
                    continue;
                }
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower(trim($word))).'%';
                $q->orWhereRaw('LOWER(text) LIKE ?', [$like]);
            }
        });

        if (empty($keywords)) {
            return collect();
        }

        return $query->orderByDesc('sent_at')->limit($limit)->get();
    }

    /**
     * Top counterparties by incoming message count (non-outgoing).
     *
     * @return Collection<int, object{sender_peer_id: string|null, sender_name: string|null, cnt: int}>
     */
    public function topIncomingContacts(int $limit = 20): Collection
    {
        return TelegramMessage::query()
            ->select([
                'sender_peer_id',
                'sender_name',
                DB::raw('COUNT(*) as cnt'),
            ])
            ->where('is_outgoing', false)
            ->whereNotNull('sender_peer_id')
            ->groupBy('sender_peer_id', 'sender_name')
            ->orderByDesc('cnt')
            ->limit($limit)
            ->get();
    }

    /**
     * Incoming senders whose messages contain the given substrings (case-insensitive).
     * When both phone and address are set, only senders matching BOTH (possibly in different messages) are returned.
     *
     * @return Collection<int, object{sender_peer_id: string, sender_name: string|null, msg_count: int, last_sent_at: string|null, telegram_chat_id: int}>
     */
    public function findPeopleByPhoneAndAddress(?string $phone, ?string $address, int $limit = 200): Collection
    {
        $phone = $phone !== null ? trim($phone) : '';
        $address = $address !== null ? trim($address) : '';

        if ($phone === '' && $address === '') {
            return collect();
        }

        $base = TelegramMessage::query()
            ->where('is_outgoing', false)
            ->whereNotNull('sender_peer_id')
            ->whereNotNull('text');

        $sets = [];
        if ($phone !== '') {
            $sets[] = $this->senderPeerIdsMatchingTextLike($base, $phone);
        }
        if ($address !== '') {
            $sets[] = $this->senderPeerIdsMatchingTextLike($base, $address);
        }

        if ($sets === []) {
            return collect();
        }

        $peerIds = $sets[0];
        foreach (array_slice($sets, 1) as $next) {
            $peerIds = $peerIds->intersect($next);
        }

        if ($peerIds->isEmpty()) {
            return collect();
        }

        return TelegramMessage::query()
            ->where('is_outgoing', false)
            ->whereIn('sender_peer_id', $peerIds->all())
            ->select([
                'sender_peer_id',
                'sender_name',
                DB::raw('COUNT(*) as msg_count'),
                DB::raw('MAX(sent_at) as last_sent_at'),
                DB::raw('MIN(telegram_chat_id) as telegram_chat_id'),
            ])
            ->groupBy('sender_peer_id', 'sender_name')
            ->orderByDesc(DB::raw('MAX(sent_at)'))
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Builder<TelegramMessage>  $base
     * @return Collection<int, string>
     */
    protected function senderPeerIdsMatchingTextLike($base, string $needle): Collection
    {
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], mb_strtolower($needle)).'%';

        return (clone $base)
            ->whereRaw('LOWER(text) LIKE ?', [$like])
            ->distinct()
            ->pluck('sender_peer_id')
            ->filter()
            ->values();
    }
}
