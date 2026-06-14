<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-o-clock class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                <span>Project Deadlines &amp; Alerts</span>
            </div>
        </x-slot>

        @if($overdueProjects->isEmpty() && $dueSoonProjects->isEmpty() && $overdueMilestones->isEmpty() && $dueSoonMilestones->isEmpty())
            {{-- All clear state --}}
            <div class="flex flex-col items-center justify-center py-10 text-center">
                <div class="mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-success-100 dark:bg-success-950/40">
                    <x-heroicon-o-check-circle class="h-8 w-8 text-success-600 dark:text-success-400" />
                </div>
                <p class="text-base font-semibold text-gray-900 dark:text-white">All projects on track!</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No projects are overdue or due in the next 14 days.</p>
            </div>
        @else

            {{-- Overdue section --}}
            @if($overdueProjects->isNotEmpty())
                <div class="mb-6">
                    <div class="mb-3 flex items-center gap-2">
                        <x-heroicon-o-exclamation-triangle class="h-4 w-4 text-danger-600 dark:text-danger-400" />
                        <h3 class="text-sm font-semibold text-danger-600 dark:text-danger-400">
                            Overdue — {{ $overdueProjects->count() }} {{ Str::plural('project', $overdueProjects->count()) }}
                        </h3>
                    </div>

                    <div class="space-y-2">
                        @foreach($overdueProjects as $project)
                            <div class="flex flex-col gap-2 rounded-xl border border-danger-200 bg-danger-50 p-3 dark:border-danger-800/60 dark:bg-danger-950/30 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ \App\Filament\Projects\Resources\ProjectResource::getUrl('edit', ['record' => $project]) }}"
                                       class="block truncate text-sm font-semibold text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400">
                                        {{ $project->name }}
                                    </a>
                                    @if($project->customer)
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $project->customer->name }}
                                        </p>
                                    @endif
                                </div>
                                <div class="flex flex-shrink-0 items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-danger-100 px-2.5 py-1 text-xs font-medium text-danger-700 dark:bg-danger-900/60 dark:text-danger-300">
                                        <x-heroicon-s-clock class="h-3 w-3" />
                                        {{ $project->end_date->diffForHumans() }}
                                    </span>
                                    <x-filament::badge color="danger">
                                        {{ str_replace('_', ' ', $project->status) }}
                                    </x-filament::badge>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Due soon section --}}
            @if($dueSoonProjects->isNotEmpty())
                <div>
                    <div class="mb-3 flex items-center gap-2">
                        <x-heroicon-o-bell-alert class="h-4 w-4 text-warning-600 dark:text-warning-400" />
                        <h3 class="text-sm font-semibold text-warning-600 dark:text-warning-400">
                            Due Soon — {{ $dueSoonProjects->count() }} {{ Str::plural('project', $dueSoonProjects->count()) }} in the next 14 days
                        </h3>
                    </div>

                    <div class="space-y-2">
                        @foreach($dueSoonProjects as $project)
                            @php
                                $daysLeft = (int) now()->diffInDays($project->end_date, false);
                                $isUrgent = $daysLeft <= 3;
                                $cardClass = $isUrgent
                                    ? 'border-warning-300 bg-warning-50 dark:border-warning-700/60 dark:bg-warning-950/30'
                                    : 'border-gray-200 bg-gray-50 dark:border-gray-700/60 dark:bg-gray-800/40';
                                $badgeClass = $isUrgent
                                    ? 'bg-warning-100 text-warning-700 dark:bg-warning-900/60 dark:text-warning-300'
                                    : 'bg-blue-100 text-blue-700 dark:bg-blue-900/60 dark:text-blue-300';
                                $endDateIso = $project->end_date->toIso8601String();
                            @endphp

                            <div class="flex flex-col gap-2 rounded-xl border {{ $cardClass }} p-3 sm:flex-row sm:items-center sm:justify-between"
                                 x-data="{
                                     endMs: new Date('{{ $endDateIso }}').getTime(),
                                     label: '',
                                     update() {
                                         const diff = this.endMs - Date.now();
                                         if (diff <= 0) { this.label = 'Due now!'; return; }
                                         const d = Math.floor(diff / 86400000);
                                         const h = Math.floor((diff % 86400000) / 3600000);
                                         const m = Math.floor((diff % 3600000) / 60000);
                                         if (d > 0) this.label = d + 'd ' + h + 'h left';
                                         else if (h > 0) this.label = h + 'h ' + m + 'm left';
                                         else this.label = m + 'm left';
                                     }
                                 }"
                                 x-init="update(); setInterval(() => update(), 30000)">
                                <div class="min-w-0 flex-1">
                                    <a href="{{ \App\Filament\Projects\Resources\ProjectResource::getUrl('edit', ['record' => $project]) }}"
                                       class="block truncate text-sm font-semibold text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400">
                                        {{ $project->name }}
                                    </a>
                                    @if($project->customer)
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                            {{ $project->customer->name }}
                                        </p>
                                    @endif
                                    <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                                        Deadline: {{ $project->end_date->format('M j, Y') }}
                                    </p>
                                </div>

                                <div class="flex flex-shrink-0 items-center gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium {{ $badgeClass }}"
                                          x-text="label"
                                          title="Live countdown">
                                        {{ $daysLeft }}d left
                                    </span>
                                    <x-filament::badge color="{{ $project->status === 'active' ? 'success' : 'gray' }}">
                                        {{ str_replace('_', ' ', $project->status) }}
                                    </x-filament::badge>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        @endif

        {{-- ─── Milestone alerts ────────────────────────────────────── --}}
        @if($overdueMilestones->isNotEmpty() || $dueSoonMilestones->isNotEmpty())
            <div class="mt-6 border-t border-gray-200 pt-5 dark:border-gray-700">
                <div class="mb-3 flex items-center gap-2">
                    <x-heroicon-o-flag class="h-4 w-4 text-gray-500 dark:text-gray-400" />
                    <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">Milestone Alerts</span>
                </div>

                @if($overdueMilestones->isNotEmpty())
                    <p class="mb-2 text-xs font-semibold text-danger-600 dark:text-danger-400">Overdue ({{ $overdueMilestones->count() }})</p>
                    <div class="mb-4 space-y-1.5">
                        @foreach($overdueMilestones as $milestone)
                            <div class="flex items-center justify-between rounded-lg border border-danger-200 bg-danger-50 px-3 py-2 dark:border-danger-800/50 dark:bg-danger-950/20">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $milestone->title }}</p>
                                    @if($milestone->project)
                                        <p class="text-xs text-gray-400">{{ $milestone->project->name }}</p>
                                    @endif
                                </div>
                                <span class="ml-3 flex-shrink-0 text-xs text-danger-600 dark:text-danger-400">
                                    {{ $milestone->due_date->diffForHumans() }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if($dueSoonMilestones->isNotEmpty())
                    <p class="mb-2 text-xs font-semibold text-warning-600 dark:text-warning-400">Due This Week ({{ $dueSoonMilestones->count() }})</p>
                    <div class="space-y-1.5">
                        @foreach($dueSoonMilestones as $milestone)
                            <div class="flex items-center justify-between rounded-lg border border-warning-200 bg-warning-50 px-3 py-2 dark:border-warning-700/50 dark:bg-warning-950/20">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">{{ $milestone->title }}</p>
                                    @if($milestone->project)
                                        <p class="text-xs text-gray-400">{{ $milestone->project->name }}</p>
                                    @endif
                                </div>
                                <span class="ml-3 flex-shrink-0 text-xs text-warning-700 dark:text-warning-400">
                                    {{ $milestone->due_date->format('M j') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

    </x-filament::section>
</x-filament-widgets::widget>
