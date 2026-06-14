<x-filament-panels::page>

    {{-- Project filter (GET form — no Livewire complexity) --}}
    <div class="flex flex-wrap items-center gap-3 rounded-xl bg-white p-3 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
        <x-heroicon-o-funnel class="h-4 w-4 flex-shrink-0 text-gray-400" />
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Filter by project:</span>
        <form method="GET" action="" class="flex items-center gap-2">
            <select name="project"
                    onchange="this.form.submit()"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                <option value="">— All my projects —</option>
                @foreach($this->getProjects() as $project)
                    <option value="{{ $project->id }}" @selected($selectedProjectId === $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
            @if($selectedProjectId)
                <a href="{{ request()->url() }}"
                   class="rounded-lg px-2.5 py-1.5 text-xs text-gray-500 ring-1 ring-gray-300 hover:bg-gray-50 dark:ring-gray-600 dark:hover:bg-gray-700">
                    Clear
                </a>
            @endif
        </form>
    </div>

    {{-- Kanban board — wire:ignore so Livewire never touches this DOM after SortableJS moves nodes --}}
    <div wire:ignore
         id="kanban-root"
         x-data="kanbanBoard({{ Illuminate\Support\Js::from($this->getBoardData()) }})"
         x-init="init()"
         class="overflow-x-auto pb-4">

        <div class="flex gap-4" style="min-width: max-content;">

            <template x-for="stage in stages" :key="stage.id">
                <div class="flex w-72 flex-shrink-0 flex-col">

                    {{-- Column header --}}
                    <div class="mb-2 flex items-center justify-between rounded-lg bg-gray-100 px-3 py-2 dark:bg-gray-700/60">
                        <span class="text-sm font-semibold text-gray-800 dark:text-gray-100" x-text="stage.name"></span>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-bold text-gray-600 shadow-sm dark:bg-gray-600 dark:text-gray-200"
                              x-text="tasksByStage(stage.id).length"></span>
                    </div>

                    {{-- Tasks drop zone --}}
                    <div class="kanban-column flex-1 space-y-2 rounded-xl bg-gray-100/60 p-2 dark:bg-gray-800/40"
                         :data-stage-id="stage.id"
                         style="min-height: 4rem;">

                        <template x-for="task in tasksByStage(stage.id)" :key="task.id">
                            <div class="task-card group cursor-grab select-none rounded-lg bg-white p-3 shadow-sm ring-1 ring-gray-900/5 transition-shadow hover:shadow-md active:cursor-grabbing dark:bg-gray-800 dark:ring-white/10"
                                 :data-task-id="task.id">

                                {{-- Title --}}
                                <a :href="task.editUrl"
                                   @click.stop
                                   class="block text-sm font-semibold text-gray-900 hover:text-primary-600 dark:text-white dark:hover:text-primary-400"
                                   x-text="task.title"></a>

                                {{-- Project name (when showing all) --}}
                                <p class="mt-0.5 truncate text-xs text-gray-400 dark:text-gray-500"
                                   x-show="task.project"
                                   x-text="task.project"></p>

                                {{-- Meta row --}}
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    {{-- Priority badge --}}
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium"
                                          :class="{
                                              'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300':   task.priority === 'urgent',
                                              'bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-300': task.priority === 'high',
                                              'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-300':   task.priority === 'normal',
                                              'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400':   task.priority === 'low',
                                          }"
                                          x-text="task.priority"></span>

                                    {{-- Due date --}}
                                    <span x-show="task.dueDate"
                                          class="inline-flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-medium"
                                          :class="{
                                              'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-300':     task.isOverdue,
                                              'bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300': task.isDueSoon && !task.isOverdue,
                                              'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300': !task.isOverdue && !task.isDueSoon,
                                          }">
                                        <x-heroicon-s-calendar class="h-3 w-3" />
                                        <span x-text="task.dueDate"></span>
                                    </span>
                                </div>

                                {{-- Assignees --}}
                                <p x-show="task.assignees"
                                   class="mt-1.5 truncate text-xs text-gray-400 dark:text-gray-500"
                                   x-text="'👤 ' + task.assignees"></p>
                            </div>
                        </template>

                        {{-- Empty state --}}
                        <p x-show="tasksByStage(stage.id).length === 0"
                           class="py-4 text-center text-xs text-gray-400 dark:text-gray-600">
                            Drop tasks here
                        </p>
                    </div>

                </div>
            </template>

            {{-- No stages state --}}
            <template x-if="stages.length === 0">
                <div class="flex w-full items-center justify-center py-16 text-gray-400">
                    <p class="text-sm">No task stages configured. Add them in ERP → Settings → Project Task Stages.</p>
                </div>
            </template>

        </div>
    </div>

    @script
    <script>
        function kanbanBoard(data) {
            return {
                stages: data.stages,
                tasks:  data.tasks,

                tasksByStage(stageId) {
                    return this.tasks.filter(t => t.stageId === stageId);
                },

                init() {
                    this.$nextTick(() => this.initSortable());
                },

                initSortable() {
                    const script = document.createElement('script');
                    script.src = 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js';
                    script.onload = () => {
                        document.querySelectorAll('.kanban-column').forEach(col => {
                            if (col._sortable) {
                                col._sortable.destroy();
                            }
                            col._sortable = Sortable.create(col, {
                                group: {
                                    name: 'kanban',
                                    pull: true,
                                    put: true,
                                },
                                animation: 180,
                                delay: 50,
                                delayOnTouchOnly: true,
                                ghostClass: 'opacity-30',
                                chosenClass: 'shadow-xl',
                                dragClass: 'rotate-1',
                                filter: 'a, button',
                                onEnd: (evt) => {
                                    if (evt.from === evt.to) return;
                                    const taskId  = parseInt(evt.item.dataset.taskId);
                                    const stageId = parseInt(evt.to.dataset.stageId);
                                    if (!taskId || !stageId) return;
                                    // Update local state so column counts stay correct
                                    const task = this.tasks.find(t => t.id === taskId);
                                    if (task) task.stageId = stageId;
                                    // Persist to DB via Livewire (fire-and-forget; wire:ignore means no re-render)
                                    $wire.moveTask(taskId, stageId);
                                },
                            });
                        });
                    };
                    if (!document.querySelector('script[src*="sortablejs"]')) {
                        document.head.appendChild(script);
                    } else if (window.Sortable) {
                        script.onload();
                    }
                },
            };
        }
    </script>
    @endscript

</x-filament-panels::page>
