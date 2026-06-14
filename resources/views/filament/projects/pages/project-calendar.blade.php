<x-filament-panels::page>

    {{-- Legend --}}
    <div class="flex flex-wrap gap-4 rounded-xl bg-white px-4 py-3 text-xs shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
        <span class="font-semibold text-gray-500 dark:text-gray-400">Legend:</span>
        <span class="flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-full bg-violet-500"></span>
            <span class="text-gray-600 dark:text-gray-300">Project deadline</span>
        </span>
        <span class="flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-full bg-orange-500"></span>
            <span class="text-gray-600 dark:text-gray-300">Due in ≤ 3 days</span>
        </span>
        <span class="flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-full bg-red-500"></span>
            <span class="text-gray-600 dark:text-gray-300">Overdue</span>
        </span>
        <span class="flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-full bg-blue-500"></span>
            <span class="text-gray-600 dark:text-gray-300">Task due date</span>
        </span>
        <span class="flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
            <span class="text-gray-600 dark:text-gray-300">Milestone due date</span>
        </span>
        <span class="flex items-center gap-1.5">
            <span class="h-3 w-3 rounded-full bg-gray-400"></span>
            <span class="text-gray-600 dark:text-gray-300">Completed / Low priority</span>
        </span>
    </div>

    {{-- Calendar container --}}
    <div wire:ignore
         class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
        <div id="projects-calendar"></div>
    </div>

    @script
    <script>
        const calendarEvents = {!! \Illuminate\Support\Js::from($this->getCalendarEvents()) !!};

        // Load FullCalendar from CDN then initialize
        const fcScript = document.createElement('script');
        fcScript.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js';
        fcScript.onload = function () {
            const isDark = document.documentElement.classList.contains('dark');

            const calendar = new FullCalendar.Calendar(
                document.getElementById('projects-calendar'),
                {
                    initialView: 'dayGridMonth',
                    height: 'auto',
                    headerToolbar: {
                        left:   'prev,next today',
                        center: 'title',
                        right:  'dayGridMonth,listMonth',
                    },
                    events: calendarEvents,
                    eventClick(info) {
                        info.jsEvent.preventDefault();
                        if (info.event.url) {
                            window.location.href = info.event.url;
                        }
                    },
                    eventDidMount(info) {
                        // Tooltip with extra info
                        const props = info.event.extendedProps;
                        const tip = props.type === 'project'
                            ? 'Project deadline — ' + info.event.title.replace('📁 ', '')
                            : 'Task: ' + info.event.title.replace('✓ ', '') + (props.project ? ' (' + props.project + ')' : '');
                        info.el.title = tip;
                    },
                    // Basic dark-mode aware colors
                    themeSystem: 'standard',
                    dayCellClassNames: isDark ? 'fc-dark-cell' : '',
                }
            );

            calendar.render();

            // Re-render on Livewire navigate (panel navigation)
            document.addEventListener('livewire:navigated', () => calendar.render());
        };

        document.head.appendChild(fcScript);
    </script>
    @endscript

    {{-- Dark mode minimal FullCalendar overrides --}}
    <style>
        .dark .fc { color: #e5e7eb; }
        .dark .fc-toolbar-title { color: #f9fafb; }
        .dark .fc-button { background: #374151 !important; border-color: #4b5563 !important; color: #e5e7eb !important; }
        .dark .fc-button:hover { background: #4b5563 !important; }
        .dark .fc-button-active { background: #6d28d9 !important; border-color: #6d28d9 !important; }
        .dark .fc-daygrid-day { background: transparent; }
        .dark .fc-daygrid-day-number { color: #9ca3af; }
        .dark .fc-col-header-cell { background: #1f2937; color: #9ca3af; }
        .dark .fc-scrollgrid { border-color: #374151 !important; }
        .dark .fc-scrollgrid td, .dark .fc-scrollgrid th { border-color: #374151 !important; }
        .dark .fc-day-today { background: rgba(109,40,217,0.1) !important; }
        .dark .fc-list-event { color: #e5e7eb; }
        .dark .fc-list-day-cushion { background: #1f2937 !important; color: #9ca3af; }
    </style>

</x-filament-panels::page>
