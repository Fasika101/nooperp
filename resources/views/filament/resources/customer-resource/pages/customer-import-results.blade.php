<x-filament-panels::page>
    @if (! $selectedImport)
        <x-filament::section>
            <x-slot name="heading">No customer imports yet</x-slot>
            <x-slot name="description">
                Import a CSV from the Customers page, then come back here to review created rows, phone matches, and failures.
            </x-slot>
        </x-filament::section>
    @else
        <div class="space-y-8">
            <x-filament::section>
                <x-slot name="heading">Import overview</x-slot>
                <x-slot name="description">
                    Review what happened during this import in plain language.
                </x-slot>

                <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="grid gap-2 text-sm text-gray-600 dark:text-gray-300">
                        <div><span class="font-medium text-gray-900 dark:text-white">File:</span> {{ $selectedImport->file_name }}</div>
                        <div><span class="font-medium text-gray-900 dark:text-white">Started:</span> {{ filled($selectedImport->created_at) ? \Illuminate\Support\Carbon::parse($selectedImport->created_at)->format('Y-m-d H:i') : '—' }}</div>
                        <div><span class="font-medium text-gray-900 dark:text-white">Completed:</span> {{ filled($selectedImport->completed_at) ? \Illuminate\Support\Carbon::parse($selectedImport->completed_at)->format('Y-m-d H:i') : '—' }}</div>
                    </div>

                    <div class="min-w-[18rem]">
                        <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="customer-import-run">
                            Show import run
                        </label>
                        <select
                            id="customer-import-run"
                            wire:model.live="selectedImportId"
                            class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 outline-none ring-1 ring-gray-950/10 transition duration-75 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:focus:ring-primary-500 sm:text-sm"
                        >
                            @foreach ($recentImports as $import)
                                <option value="{{ $import['id'] }}">
                                    #{{ $import['id'] }} • {{ filled($import['created_at'] ?? null) ? \Illuminate\Support\Carbon::parse($import['created_at'])->format('Y-m-d H:i') : 'Unknown time' }} • {{ $import['file_name'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Rows in file</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($summary['total_rows'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Imported successfully</div>
                        <div class="mt-2 text-2xl font-semibold text-green-600 dark:text-green-400">{{ number_format($summary['successful_rows'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Failed rows</div>
                        <div class="mt-2 text-2xl font-semibold text-red-600 dark:text-red-400">{{ number_format($summary['failed_rows'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="text-sm text-gray-500 dark:text-gray-400">New customers created</div>
                        <div class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ number_format($summary['created'] ?? 0) }}</div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Existing phone matches</div>
                        <div class="mt-2 text-2xl font-semibold text-amber-600 dark:text-amber-400">{{ number_format($summary['phone_matches_total'] ?? 0) }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Renamed {{ number_format($summary['phone_name_replaced'] ?? 0) }},
                            kept name {{ number_format($summary['phone_name_kept'] ?? 0) }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">
                        <div class="text-sm text-gray-500 dark:text-gray-400">Existing email matches</div>
                        <div class="mt-2 text-2xl font-semibold text-sky-600 dark:text-sky-400">{{ number_format($summary['email_matches_total'] ?? 0) }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Updated {{ number_format($summary['email_match_updated'] ?? 0) }},
                            no change {{ number_format($summary['email_match_no_change'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Phone matches that renamed customers</x-slot>
                <x-slot name="description">
                    These rows found an existing customer by phone number and replaced the stored name.
                </x-slot>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Phone</th>
                                <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Old name</th>
                                <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Imported name</th>
                                <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Note</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @forelse ($phoneNameReplacedRows as $row)
                                <tr>
                                    <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $row->row_phone ?: '—' }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $row->previous_name ?: '—' }}</td>
                                    <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $row->current_name ?: ($row->row_name ?: '—') }}</td>
                                    <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $row->note ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No customer names were replaced by phone match in this import.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Existing matches kept or synced</x-slot>
                <x-slot name="description">
                    Rows that matched existing customers without a phone-based rename.
                </x-slot>

                <div class="grid gap-6 xl:grid-cols-2">
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr>
                                    <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Phone</th>
                                    <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Current name</th>
                                    <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Result</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @forelse ($phoneNameKeptRows as $row)
                                    <tr>
                                        <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ $row->row_phone ?: '—' }}</td>
                                        <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $row->current_name ?: '—' }}</td>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $row->note ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-6 text-center text-gray-500">No existing phone matches kept their names in this import.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-white/5">
                                <tr>
                                    <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Email</th>
                                    <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Customer</th>
                                    <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Result</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                                @forelse ($emailMatchRows as $row)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $row->row_email ?: '—' }}</td>
                                        <td class="px-4 py-2 text-gray-900 dark:text-white">{{ $row->current_name ?: ($row->row_name ?: '—') }}</td>
                                        <td class="px-4 py-2 text-gray-600 dark:text-gray-400">{{ $row->note ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-6 text-center text-gray-500">No existing email matches were found in this import.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Failed rows</x-slot>
                <x-slot name="description">
                    These rows were rejected and were not imported.
                </x-slot>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Name</th>
                                <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Phone</th>
                                <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Email</th>
                                <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @forelse ($failedRows as $row)
                                <tr class="align-top">
                                    <td class="px-4 py-2 text-gray-900 dark:text-white">{{ data_get($row->data, 'name') ?: '—' }}</td>
                                    <td class="px-4 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">{{ data_get($row->data, 'phone') ?: '—' }}</td>
                                    <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ data_get($row->data, 'email') ?: '—' }}</td>
                                    <td class="px-4 py-2 text-red-600 dark:text-red-400">{{ $row->validation_error ?: 'Unknown error' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500">No failed rows in this import.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Recent import runs</x-slot>
                <x-slot name="description">
                    Quick comparison between your latest customer imports.
                </x-slot>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Run</th>
                                <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">When</th>
                                <th class="px-4 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Success</th>
                                <th class="px-4 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Failed</th>
                                <th class="px-4 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Created</th>
                                <th class="px-4 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Phone renamed</th>
                                <th class="px-4 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Email matched</th>
                                <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                            @foreach ($recentImports as $import)
                                <tr @class(['bg-primary-50/50 dark:bg-primary-500/10' => (int) $import['id'] === (int) $selectedImportId])>
                                    <td class="px-4 py-2 text-gray-900 dark:text-white">#{{ $import['id'] }}<div class="text-xs text-gray-500">{{ $import['file_name'] }}</div></td>
                                    <td class="whitespace-nowrap px-4 py-2 text-gray-600 dark:text-gray-400">{{ filled($import['created_at'] ?? null) ? \Illuminate\Support\Carbon::parse($import['created_at'])->format('Y-m-d H:i') : '—' }}</td>
                                    <td class="px-4 py-2 text-end tabular-nums text-green-600 dark:text-green-400">{{ number_format($import['successful_rows']) }}</td>
                                    <td class="px-4 py-2 text-end tabular-nums text-red-600 dark:text-red-400">{{ number_format($import['failed_rows']) }}</td>
                                    <td class="px-4 py-2 text-end tabular-nums">{{ number_format($import['created']) }}</td>
                                    <td class="px-4 py-2 text-end tabular-nums">{{ number_format($import['phone_name_replaced']) }}</td>
                                    <td class="px-4 py-2 text-end tabular-nums">{{ number_format($import['email_matches_total']) }}</td>
                                    <td class="px-4 py-2">
                                        <button
                                            type="button"
                                            wire:click="selectImport({{ $import['id'] }})"
                                            class="text-primary-600 hover:underline dark:text-primary-400"
                                        >
                                            View details
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>
