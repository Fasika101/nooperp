@php
    use App\Filament\Resources\ProductResource;
    use Illuminate\Support\Number;

    $currency = \App\Models\Setting::getDefaultCurrency();
    $inventory = $report['inventory'] ?? ['unique_products' => 0, 'total_quantity' => 0];
    $sales = $report['sales'] ?? ['units_sold' => 0, 'revenue' => 0];
    $topProducts = $report['top_products'] ?? collect();
    $monthLabel = $report['month_label'] ?? now()->format('F Y');
    $branchScope = $report['branch_scope_label'] ?? '—';
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Filters</x-slot>
            <x-slot name="description">
                {{ $monthLabel }} · {{ $branchScope }} · inventory products only (services excluded)
            </x-slot>

            @if ($branchOptions === [])
                <p class="text-sm text-gray-500 dark:text-gray-400">No branches are available for your account.</p>
            @else
                <div class="max-w-md">
                    <label class="fi-fo-field-wrp-label mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300" for="product-stats-branch">
                        Branch
                    </label>
                    <select
                        id="product-stats-branch"
                        wire:model.live="branchFilter"
                        class="fi-input block w-full rounded-lg border-none bg-white px-3 py-2 text-base text-gray-950 outline-none ring-1 ring-gray-950/10 transition duration-75 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20 dark:focus:ring-primary-500 sm:text-sm"
                    >
                        @foreach ($branchOptions as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Overview</x-slot>
            <x-slot name="description">Stock on hand and sales for {{ $branchScope }}.</x-slot>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Unique products</div>
                    <div class="fi-wi-stats-overview-stat-value mt-2 text-2xl font-semibold tracking-tight tabular-nums text-gray-950 dark:text-white">
                        {{ number_format($inventory['unique_products'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">With stock on hand</div>
                </div>

                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Total quantity</div>
                    <div class="fi-wi-stats-overview-stat-value mt-2 text-2xl font-semibold tracking-tight tabular-nums text-gray-950 dark:text-white">
                        {{ number_format($inventory['total_quantity'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">All variants combined</div>
                </div>

                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Units sold</div>
                    <div class="fi-wi-stats-overview-stat-value mt-2 text-2xl font-semibold tracking-tight tabular-nums text-gray-950 dark:text-white">
                        {{ number_format($sales['units_sold'] ?? 0) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">This month</div>
                </div>

                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Sales revenue</div>
                    <div class="fi-wi-stats-overview-stat-value mt-2 text-2xl font-semibold tracking-tight tabular-nums text-gray-950 dark:text-white">
                        {{ Number::currency($sales['revenue'] ?? 0, $currency) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Completed POS orders</div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Top 10 products</x-slot>
            <x-slot name="description">{{ $monthLabel }} · ranked by units sold</x-slot>

            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full min-w-[36rem] text-sm">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">#</th>
                            <th class="px-4 py-2 text-start font-medium text-gray-700 dark:text-gray-300">Product</th>
                            <th class="px-4 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Units sold</th>
                            <th class="px-4 py-2 text-end font-medium text-gray-700 dark:text-gray-300">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($topProducts as $index => $row)
                            <tr class="align-middle">
                                <td class="px-4 py-2 tabular-nums text-gray-500 dark:text-gray-400">{{ $index + 1 }}</td>
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">
                                    @if (! empty($row->product_id))
                                        <a
                                            href="{{ ProductResource::getUrl('view', ['record' => $row->product_id]) }}"
                                            class="text-primary-600 hover:underline dark:text-primary-400"
                                        >
                                            {{ $row->product_name }}
                                        </a>
                                    @else
                                        {{ $row->product_name ?? '—' }}
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-end tabular-nums text-gray-900 dark:text-white">
                                    {{ number_format((int) $row->units_sold) }}
                                </td>
                                <td class="px-4 py-2 text-end tabular-nums font-medium text-gray-900 dark:text-white">
                                    {{ Number::currency((float) $row->revenue, $currency) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">
                                    No inventory product sales recorded this month for the selected branch scope.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($topProducts->isNotEmpty())
                        <tfoot class="bg-gray-50 font-medium dark:bg-white/5">
                            <tr>
                                <td colspan="2" class="px-4 py-2 text-gray-700 dark:text-gray-300">Top 10 total</td>
                                <td class="px-4 py-2 text-end tabular-nums text-gray-900 dark:text-white">
                                    {{ number_format($topProducts->sum('units_sold')) }}
                                </td>
                                <td class="px-4 py-2 text-end tabular-nums text-gray-900 dark:text-white">
                                    {{ Number::currency($topProducts->sum('revenue'), $currency) }}
                                </td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>

            <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                Updated {{ now()->format('d M Y, H:i') }}. Stock includes all variants; color and size breakdown is not included.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
