@php
    use App\Filament\Resources\TelegramChatResource;
@endphp

<x-filament-panels::page>
    <div class="space-y-8">
        <x-filament::section>
            <x-slot name="heading">Find people (phone &amp; address)</x-slot>
            <x-slot name="description">
                Search <strong>incoming</strong> messages by text: type part of a <strong>phone number</strong> (e.g. <code class="text-xs">0911</code> or <code class="text-xs">+251</code>) and/or an <strong>address</strong> phrase (e.g. <code class="text-xs">Bole</code>, street). If both are filled, only senders who match <strong>both</strong> (in any of their messages) are listed.
            </x-slot>

            <div class="mb-4 flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end">
                <div class="min-w-[12rem] flex-1">
                    <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="crm-phone-filter">Phone contains</label>
                    <input
                        id="crm-phone-filter"
                        type="text"
                        wire:model.live.debounce.400ms="phoneFilter"
                        placeholder="e.g. 0911 or +251"
                        class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 outline-none ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500 dark:focus:ring-primary-500 sm:text-sm"
                    />
                </div>
                <div class="min-w-[12rem] flex-1">
                    <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="crm-address-filter">Address contains</label>
                    <input
                        id="crm-address-filter"
                        type="text"
                        wire:model.live.debounce.400ms="addressFilter"
                        placeholder="e.g. Bole, street, woreda…"
                        class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 outline-none ring-1 ring-gray-950/10 transition duration-75 placeholder:text-gray-400 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:placeholder:text-gray-500 dark:focus:ring-primary-500 sm:text-sm"
                    />
                </div>
                <div>
                    <button
                        type="button"
                        wire:click="clearContactFilters"
                        class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-btn-color-gray fi-btn-size-md fi-btn-outlined gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-white text-gray-950 ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-white dark:ring-white/20"
                    >
                        Clear
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Sender</th>
                            <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Peer ID</th>
                            <th class="px-4 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Msgs</th>
                            <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Last message</th>
                            <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Chat</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @if ($phoneFilter === '' && $addressFilter === '')
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">Enter phone and/or address above to search.</td>
                            </tr>
                        @elseif ($matchedPeople->isEmpty())
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500">No senders match these filters.</td>
                            </tr>
                        @else
                            @foreach ($matchedPeople as $row)
                                <tr class="align-middle">
                                    <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $row->sender_name ?? '—' }}</td>
                                    <td class="px-4 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $row->sender_peer_id }}</td>
                                    <td class="px-4 py-2 text-end tabular-nums">{{ number_format((int) $row->msg_count) }}</td>
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-600 dark:text-gray-400">
                                        @if (! empty($row->last_sent_at))
                                            {{ \Illuminate\Support\Carbon::parse($row->last_sent_at)->format('Y-m-d H:i') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-4 py-2">
                                        @if (! empty($row->telegram_chat_id))
                                            <a
                                                href="{{ TelegramChatResource::getUrl('view', ['record' => (int) $row->telegram_chat_id]) }}"
                                                class="text-primary-600 hover:underline dark:text-primary-400"
                                            >Open chat</a>
                                        @else
                                            —
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Product / optical keywords</x-slot>
            <x-slot name="description">Counts from imported message text (config: <code class="text-xs">config/telegram_crm.php</code>).</x-slot>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Keyword</th>
                            <th class="px-4 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Matches</th>
                            <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Sample snippets</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($keywordStats as $kw => $row)
                            <tr class="align-top">
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $kw }}</td>
                                <td class="px-4 py-2 text-end tabular-nums">{{ number_format($row['count']) }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-400">
                                    @foreach ($row['samples'] as $msg)
                                        <div class="mb-1 line-clamp-2" title="{{ $msg->text }}">
                                            {{ \Illuminate\Support\Str::limit($msg->text, 120) }}
                                        </div>
                                    @endforeach
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-gray-500">No data — import a Telegram export first.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Top incoming contacts</x-slot>
            <x-slot name="description">By number of non-outgoing messages (sender fields from export).</x-slot>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Sender</th>
                            <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Peer ID</th>
                            <th class="px-4 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Messages</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($topContacts as $row)
                            <tr>
                                <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $row->sender_name ?? '—' }}</td>
                                <td class="px-4 py-2 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $row->sender_peer_id }}</td>
                                <td class="px-4 py-2 text-end tabular-nums">{{ number_format($row->cnt) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-gray-500">No incoming messages with sender peer ID.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Address / location hints</x-slot>
            <x-slot name="description">Messages matching address keywords in <code class="text-xs">config/telegram_crm.php</code> (tune for your region).</x-slot>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">When</th>
                            <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Text</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($addressHints as $msg)
                            <tr class="align-top">
                                <td class="whitespace-nowrap px-4 py-2 text-gray-600 dark:text-gray-400">
                                    {{ $msg->sent_at?->format('Y-m-d H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $msg->text }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2" class="px-4 py-6 text-center text-gray-500">No matches.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
