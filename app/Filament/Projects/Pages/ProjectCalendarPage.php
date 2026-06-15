<?php

namespace App\Filament\Projects\Pages;

use App\Filament\Projects\Resources\CalendarEventResource;
use App\Filament\Projects\Resources\ProjectResource;
use App\Models\CalendarEvent;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\ProjectTask;
use Filament\Pages\Page;

class ProjectCalendarPage extends Page
{
    protected string $view = 'filament.projects.pages.project-calendar';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static string|\UnitEnum|null $navigationGroup = 'My Work';

    protected static ?string $navigationLabel = 'Calendar';

    protected static ?string $title = 'Project Calendar';

    protected static ?int $navigationSort = 30;

    public function getCalendarEvents(): array
    {
        $user       = auth()->user();
        $uid        = $user->id;
        $isSuperAdmin = $user->hasRole('super_admin');
        $events     = [];

        // ── Project deadlines ────────────────────────────────────────────────
        $projectQuery = Project::query()
            ->whereNotNull('end_date')
            ->whereNotIn('status', [Project::STATUS_CANCELLED]);

        if (! $isSuperAdmin) {
            $projectQuery->where(function ($q) use ($uid) {
                $q->where('created_by', $uid)
                    ->orWhereHas('members', fn ($m) => $m->whereKey($uid));
            });
        }

        $projectQuery
            ->get()
            ->each(function (Project $project) use (&$events) {
                $color = match (true) {
                    $project->status === Project::STATUS_COMPLETED => '#6b7280',
                    $project->end_date->isPast()                   => '#ef4444',
                    $project->end_date->lte(now()->addDays(3))     => '#f97316',
                    default                                        => '#8b5cf6',
                };
                $events[] = [
                    'id'              => 'proj-' . $project->id,
                    'title'           => '📁 ' . $project->name,
                    'start'           => $project->end_date->toDateString(),
                    'allDay'          => true,
                    'url'             => ProjectResource::getUrl('edit', ['record' => $project]),
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    'textColor'       => '#ffffff',
                    'extendedProps'   => ['type' => 'project', 'status' => $project->status],
                ];
            });

        // ── Task due dates ───────────────────────────────────────────────────
        $taskQuery = ProjectTask::query()
            ->whereNotNull('due_date')
            ->with('project:id,name');

        if (! $isSuperAdmin) {
            $taskQuery->whereHas('project', function ($q) use ($uid) {
                $q->where('created_by', $uid)
                    ->orWhereHas('members', fn ($m) => $m->whereKey($uid));
            });
        }

        $taskQuery
            ->get()
            ->each(function (ProjectTask $task) use (&$events) {
                $color = match ($task->priority) {
                    'urgent' => '#ef4444',
                    'high'   => '#f97316',
                    'low'    => '#6b7280',
                    default  => '#3b82f6',
                };
                if ($task->due_date->isPast()) {
                    $color = '#dc2626';
                }
                $events[] = [
                    'id'              => 'task-' . $task->id,
                    'title'           => '✓ ' . $task->title,
                    'start'           => $task->due_date->toDateString(),
                    'allDay'          => true,
                    'url'             => ProjectResource::getUrl('edit', ['record' => $task->project_id]),
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    'textColor'       => '#ffffff',
                    'extendedProps'   => ['type' => 'task', 'project' => $task->project?->name ?? ''],
                ];
            });

        // ── Milestone due dates ──────────────────────────────────────────────
        $milestoneQuery = ProjectMilestone::query()
            ->whereNotNull('due_date')
            ->whereNull('completed_at')
            ->with('project:id,name');

        if (! $isSuperAdmin) {
            $milestoneQuery->whereHas('project', function ($q) use ($uid) {
                $q->where('created_by', $uid)
                    ->orWhereHas('members', fn ($m) => $m->whereKey($uid));
            });
        }

        $milestoneQuery
            ->get()
            ->each(function (ProjectMilestone $milestone) use (&$events) {
                $color = $milestone->due_date->isPast() ? '#ef4444' : '#10b981';
                $events[] = [
                    'id'              => 'milestone-' . $milestone->id,
                    'title'           => '🚩 ' . $milestone->title,
                    'start'           => $milestone->due_date->toDateString(),
                    'allDay'          => true,
                    'url'             => ProjectResource::getUrl('edit', ['record' => $milestone->project_id]),
                    'backgroundColor' => $color,
                    'borderColor'     => $color,
                    'textColor'       => '#ffffff',
                    'extendedProps'   => [
                        'type'    => 'milestone',
                        'project' => $milestone->project?->name ?? '',
                    ],
                ];
            });

        // ── User calendar events (own + invited; super_admin sees all) ────────
        $calEventQuery = CalendarEvent::query()
            ->with(['creator:id,name', 'attendees:id,name']);

        if (! $isSuperAdmin) {
            $calEventQuery->where(function ($q) use ($uid) {
                $q->where('created_by', $uid)
                    ->orWhereHas('attendees', fn ($a) => $a->whereKey($uid));
            });
        }

        $calEventQuery
            ->get()
            ->each(function (CalendarEvent $ev) use (&$events) {
                $end = $ev->end_date
                    ? $ev->end_date->addDay()->toDateString()   // FullCalendar end is exclusive
                    : null;

                $start = $ev->isAllDay()
                    ? $ev->start_date->toDateString()
                    : $ev->start_date->toDateString() . 'T' . $ev->start_time;

                $endVal = null;
                if ($ev->isAllDay()) {
                    $endVal = $end;
                } elseif ($ev->end_time) {
                    $endVal = ($ev->end_date ?? $ev->start_date)->toDateString() . 'T' . $ev->end_time;
                }

                $attendeeNames = $ev->attendees->pluck('name')->implode(', ');

                $events[] = array_filter([
                    'id'              => 'event-' . $ev->id,
                    'title'           => '📅 ' . $ev->title,
                    'start'           => $start,
                    'end'             => $endVal,
                    'allDay'          => $ev->isAllDay(),
                    'url'             => CalendarEventResource::getUrl('edit', ['record' => $ev->id]),
                    'backgroundColor' => $ev->hexColor(),
                    'borderColor'     => $ev->hexColor(),
                    'textColor'       => '#ffffff',
                    'extendedProps'   => [
                        'type'        => 'event',
                        'description' => $ev->description,
                        'location'    => $ev->location,
                        'createdBy'   => $ev->creator?->name,
                        'attendees'   => $attendeeNames,
                    ],
                ], fn ($v) => $v !== null);
            });

        // ── Ethiopian public holidays ─────────────────────────────────────────
        $year = (int) now()->format('Y');

        foreach ($this->getEthiopianHolidays($year) as $holiday) {
            $events[] = $holiday;
        }

        // Also include next year's so the calendar stays useful when looking ahead
        if ((int) now()->format('m') >= 9) {
            foreach ($this->getEthiopianHolidays($year + 1) as $holiday) {
                $events[] = $holiday;
            }
        }

        return $events;
    }

    /**
     * Returns Ethiopian public holidays for a given Gregorian year.
     * Dates follow the Ethiopian calendar converted to Gregorian.
     * Fixed holidays use their standard Gregorian equivalents.
     */
    private function getEthiopianHolidays(int $year): array
    {
        $holidays = [
            // ── Ethiopian Orthodox & Cultural ────────────────────────────────
            // Enkutatash (Ethiopian New Year) — Meskerem 1 = Sep 11 (Sep 12 in leap year)
            ['date' => $this->leapAdj($year, '09-11', '09-12'), 'name' => 'Enkutatash (Ethiopian New Year)'],
            // Meskel (Finding of the True Cross) — Meskerem 17 = Sep 27
            ['date' => "{$year}-09-27", 'name' => 'Meskel'],
            // Timkat (Ethiopian Epiphany) — Terr 11 = Jan 19 (Jan 20 leap)
            ['date' => $this->leapAdj($year, '01-19', '01-20'), 'name' => 'Timkat (Ethiopian Epiphany)'],
            // Ethiopian Christmas (Genna) — Tahsas 29 = Jan 7
            ['date' => "{$year}-01-07", 'name' => 'Genna (Ethiopian Christmas)'],
            // Adwa Victory Day — March 2
            ['date' => "{$year}-03-02", 'name' => 'Adwa Victory Day'],

            // ── National Holidays ────────────────────────────────────────────
            // Labour Day / International Workers Day — May 1
            ['date' => "{$year}-05-01", 'name' => 'Labour Day'],
            // Patriots Victory Day — May 5
            ['date' => "{$year}-05-05", 'name' => "Patriots' Victory Day"],
            // Downfall of the Derg — May 28
            ['date' => "{$year}-05-28", 'name' => 'Derg Downfall Day'],

            // ── Islamic Holidays (approximate Gregorian, shift each year) ────
            // These are recalculated each year using estimated offsets.
            // For a production app consider using a Hijri library.
        ];

        // Approximate Islamic holidays (based on 2024 baseline, ~11 days earlier each year)
        $islamicHolidays = $this->approximateIslamicHolidays($year);

        $result = [];
        foreach (array_merge($holidays, $islamicHolidays) as $h) {
            $result[] = [
                'id'              => 'holiday-' . md5($h['name'] . $h['date']),
                'title'           => '🇪🇹 ' . $h['name'],
                'start'           => $h['date'],
                'allDay'          => true,
                'backgroundColor' => '#15803d',
                'borderColor'     => '#15803d',
                'textColor'       => '#ffffff',
                'extendedProps'   => ['type' => 'holiday'],
                'display'         => 'background', // renders as a background band — unobtrusive
            ];
        }

        return $result;
    }

    /**
     * Returns Sep-11 in non-leap years and Sep-12 in leap years
     * (Enkutatash / Timkat adjustment).
     */
    private function leapAdj(int $year, string $normal, string $leap): string
    {
        return "{$year}-" . (\Carbon\Carbon::create($year)->isLeapYear() ? $leap : $normal);
    }

    /**
     * Approximate Islamic holidays for Ethiopia.
     * 2024 baselines: Eid al-Fitr Apr 10, Eid al-Adha Jun 17, Mawlid Sep 15.
     * Each Hijri year is ~354 days (≈11 days less than Gregorian).
     */
    private function approximateIslamicHolidays(int $year): array
    {
        $delta = $year - 2024;

        $eidFitr   = \Carbon\Carbon::parse('2024-04-10')->addDays($delta * -11);
        $eidAdha   = \Carbon\Carbon::parse('2024-06-17')->addDays($delta * -11);
        $mawlid    = \Carbon\Carbon::parse('2024-09-15')->addDays($delta * -11);

        // Keep dates within the target year or adjacent (holiday might wrap)
        return [
            ['date' => $eidFitr->format('Y-m-d'), 'name' => 'Eid al-Fitr'],
            ['date' => $eidAdha->format('Y-m-d'), 'name' => 'Eid al-Adha'],
            ['date' => $mawlid->format('Y-m-d'),  'name' => 'Mawlid (Prophet\'s Birthday)'],
        ];
    }
}
