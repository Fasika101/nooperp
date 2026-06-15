<x-filament-panels::page>

    {{-- Toolbar: header with New Event button --}}
    <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10 px-4 py-3 space-y-3">
        {{-- Top row: title + button --}}
        <div class="flex items-center justify-between gap-3">
            <span class="text-sm font-semibold text-gray-500 dark:text-gray-400">Legend</span>
            <a href="{{ \App\Filament\Projects\Resources\CalendarEventResource::getUrl('create') }}"
               class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-primary-700 dark:bg-primary-500 dark:hover:bg-primary-600 shrink-0">
                <x-heroicon-o-plus class="h-4 w-4" />
                <span>New Event</span>
            </a>
        </div>
        {{-- Legend dots — wraps nicely on small screens --}}
        <div class="flex flex-wrap gap-x-4 gap-y-2 text-xs">
            <span class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-violet-500 shrink-0"></span>
                <span class="text-gray-600 dark:text-gray-300">Deadline</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-orange-500 shrink-0"></span>
                <span class="text-gray-600 dark:text-gray-300">Due soon</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-red-500 shrink-0"></span>
                <span class="text-gray-600 dark:text-gray-300">Overdue</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-blue-500 shrink-0"></span>
                <span class="text-gray-600 dark:text-gray-300">Task</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 shrink-0"></span>
                <span class="text-gray-600 dark:text-gray-300">Milestone</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-green-700 shrink-0"></span>
                <span class="text-gray-600 dark:text-gray-300">🇪🇹 Holiday</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-gray-400 shrink-0"></span>
                <span class="text-gray-600 dark:text-gray-300">Done / Low</span>
            </span>
            <span class="flex items-center gap-1.5">
                <span class="h-2.5 w-2.5 rounded-full bg-fuchsia-500 shrink-0"></span>
                <span class="text-gray-600 dark:text-gray-300">📅 My event</span>
            </span>
        </div>
    </div>

    {{-- Event detail popup (hidden by default) --}}
    <div id="event-popup"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40"
         onclick="if(event.target===this) closePopup()">
        <div class="relative w-full max-w-md mx-4 sm:mx-0 rounded-2xl bg-white p-5 shadow-2xl dark:bg-gray-800">
            <button onclick="closePopup()"
                    class="absolute right-4 top-4 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
            <h3 id="popup-title" class="text-lg font-bold text-gray-900 dark:text-white mb-3"></h3>
            <dl class="space-y-1.5 text-sm text-gray-700 dark:text-gray-300" id="popup-body"></dl>
            <div class="mt-4 flex gap-2" id="popup-actions"></div>
        </div>
    </div>

    {{-- Calendar container --}}
    <div wire:ignore
         class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-900/5 dark:bg-gray-800 dark:ring-white/10">
        <div id="projects-calendar"></div>
    </div>

    @script
    <script>
        const calendarEvents    = {!! \Illuminate\Support\Js::from($this->getCalendarEvents()) !!};
        const createEventUrl    = {!! \Illuminate\Support\Js::from(\App\Filament\Projects\Resources\CalendarEventResource::getUrl('create')) !!};

        function closePopup() {
            document.getElementById('event-popup').classList.add('hidden');
            document.getElementById('event-popup').classList.remove('flex');
        }

        function showPopup(info) {
            const ev    = info.event;
            const props = ev.extendedProps;

            // Don't popup for background holiday bands — just navigate if URL
            if (props.type === 'holiday') return;

            info.jsEvent.preventDefault();

            document.getElementById('popup-title').textContent = ev.title;

            const rows = [];

            if (props.type === 'event') {
                if (props.description) rows.push(['Description', props.description]);
                if (props.location)    rows.push(['Location', props.location]);
                if (props.createdBy)   rows.push(['Organiser', props.createdBy]);
                if (props.attendees)   rows.push(['Attendees', props.attendees]);
            } else if (props.type === 'project') {
                rows.push(['Status', props.status ?? '']);
            } else if (props.type === 'task') {
                if (props.project) rows.push(['Project', props.project]);
            } else if (props.type === 'milestone') {
                if (props.project) rows.push(['Project', props.project]);
            }

            document.getElementById('popup-body').innerHTML = rows.map(([label, val]) =>
                `<div class="flex gap-2"><dt class="font-medium text-gray-500 dark:text-gray-400 w-24 flex-shrink-0">${label}</dt><dd>${val}</dd></div>`
            ).join('');

            const actionsEl = document.getElementById('popup-actions');
            actionsEl.innerHTML = '';
            if (ev.url) {
                const link = document.createElement('a');
                link.href = ev.url;
                link.textContent = props.type === 'event' ? 'Edit event' : 'Open';
                link.className = 'inline-flex items-center rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-primary-700';
                actionsEl.appendChild(link);
            }
            const closeBtn = document.createElement('button');
            closeBtn.textContent = 'Close';
            closeBtn.onclick = closePopup;
            closeBtn.className = 'inline-flex items-center rounded-lg bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600';
            actionsEl.appendChild(closeBtn);

            document.getElementById('event-popup').classList.remove('hidden');
            document.getElementById('event-popup').classList.add('flex');
        }

        // Load FullCalendar from CDN then initialize
        const fcScript = document.createElement('script');
        fcScript.src = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js';
        fcScript.onload = function () {
            const isDark = document.documentElement.classList.contains('dark');

            const isMobile = window.innerWidth < 640;

            const calendar = new FullCalendar.Calendar(
                document.getElementById('projects-calendar'),
                {
                    initialView: isMobile ? 'listMonth' : 'dayGridMonth',
                    height: 'auto',
                    headerToolbar: isMobile
                        ? { left: 'prev,next', center: 'title', right: 'today' }
                        : { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listMonth' },
                    footerToolbar: isMobile
                        ? { center: 'dayGridMonth,listMonth' }
                        : false,
                    eventDisplay: 'block',
                    dayMaxEvents: isMobile ? 2 : true,
                    events: calendarEvents,

                    // Click empty day → open New Event form pre-filled with that date
                    dateClick(info) {
                        window.location.href = createEventUrl + '?date=' + info.dateStr;
                    },

                    // Click event → show popup (non-holidays) or follow URL
                    eventClick(info) {
                        const props = info.event.extendedProps;
                        if (props.type === 'holiday') {
                            info.jsEvent.preventDefault();
                            return;
                        }
                        showPopup(info);
                    },

                    eventDidMount(info) {
                        const props = info.event.extendedProps;
                        if (props.type === 'holiday') {
                            info.el.style.cursor = 'default';
                        }
                    },

                    themeSystem: 'standard',
                    dayCellClassNames: isDark ? 'fc-dark-cell' : '',
                }
            );

            calendar.render();

            document.addEventListener('livewire:navigated', () => calendar.render());
        };

        document.head.appendChild(fcScript);
    </script>
    @endscript

    {{-- Dark mode + mobile FullCalendar overrides --}}
    <style>
        /* ── Dark mode ── */
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
        /* Holiday background bands */
        .fc-bg-event { opacity: 0.18 !important; cursor: default !important; }

        /* ── Mobile ── */
        @media (max-width: 639px) {
            /* Smaller toolbar title so it fits on one line */
            .fc-toolbar-title { font-size: 1rem !important; }

            /* Compact nav buttons */
            .fc-button {
                padding: 0.25rem 0.5rem !important;
                font-size: 0.75rem !important;
            }

            /* Day-number fits in tiny cells */
            .fc-daygrid-day-number {
                font-size: 0.7rem;
                padding: 2px !important;
            }

            /* Day-of-week header labels: show only 1 letter (Su → S) */
            .fc-col-header-cell-cushion {
                font-size: 0.65rem;
                padding: 4px 2px !important;
            }

            /* Event pills: clip long titles instead of overflowing */
            .fc-event-title { font-size: 0.65rem; }

            /* List view: slightly tighter rows */
            .fc-list-event td { padding: 6px 8px !important; font-size: 0.8rem; }
            .fc-list-day-cushion { padding: 6px 8px !important; font-size: 0.8rem; }
        }
    </style>

</x-filament-panels::page>
