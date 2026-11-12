<?php

namespace App\Http\Controllers;

use App\Support\CeAnalytics;
use App\Support\DataExporter;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CrewDataController extends Controller
{
    public function index(Request $request): View|StreamedResponse|Response
    {
        if ($request->filled('export')) {
            return $this->export($request);
        }

        $period = ReportPeriod::resolve($request);
        $search = trim((string) $request->query('q', ''));
        $roleFilter = trim((string) $request->query('role', ''));
        $engagementFilter = trim((string) $request->query('engagement', ''));

        [$byPerson, $byRole, $crewLines, $bookingIds, $needsRoleCount] =
            $this->crewByPersonAndRole($period, $search, $roleFilter, $engagementFilter);

        $roleOptions = DB::table('crew_positions')->orderBy('position_name')->pluck('position_name');
        $engagementOptions = ['staff' => 'Staff', 'freelance' => 'Freelance', 'on_call' => 'On-Call'];

        $spendTotal = (float) $crewLines->sum(fn ($r) => $r->rate_used * $r->paid_days);

        $chartMonths = $period['chart_months'];
        $chartKeys = ReportPeriod::chartMonthKeys($chartMonths);
        $chartCes = CeAnalytics::confirmedCes(
            ReportPeriod::chartFrom($chartMonths)->toDateTimeString(),
            now()->endOfDay()->toDateTimeString()
        );
        $chartBookingIds = $chartCes->pluck('booking_id');
        $chartCrewLines = DB::table('booking_crew')
            ->whereIn('booking_id', $chartBookingIds)
            ->select('booking_id', 'crew_id', 'rate_used', 'hours_worked')
            ->get();
        $chartNoShowDays = DB::table('crew_attendance')
            ->whereIn('booking_id', $chartBookingIds)
            ->whereIn('status', ['no_show', 'back_out'])
            ->select('booking_id', 'crew_id')
            ->get()
            ->countBy(fn ($r) => $r->booking_id . '-' . $r->crew_id);
        foreach ($chartCrewLines as $line) {
            $bad = $chartNoShowDays->get($line->booking_id . '-' . $line->crew_id, 0);
            $line->paid_days = max(0.0, (float) $line->hours_worked - $bad);
        }

        $monthly = collect($chartKeys)->map(function ($key) use ($chartCes, $chartCrewLines) {
            $ids = $chartCes->filter(fn ($c) => \Illuminate\Support\Carbon::parse($c->confirmed_at)->format('Y-m') === $key)
                ->pluck('booking_id');

            return (object) [
                'sort_key' => $key,
                'label' => \Illuminate\Support\Carbon::createFromFormat('Y-m', $key)->format('M Y'),
                'spend' => (float) $chartCrewLines->whereIn('booking_id', $ids)->sum(fn ($r) => $r->rate_used * $r->paid_days),
            ];
        });

        $crewBooked = $crewLines->pluck('crew_id')->unique()->count();

        $crewAssignedShoots = $crewLines->pluck('booking_id')->unique()->count();
        $shootsNeedingCrew = max(0, $bookingIds->count() - $crewAssignedShoots);

        $periodSummaryShoots = $chartBookingIds->unique()->count();
        $periodSummaryAssigned = $chartCrewLines->pluck('booking_id')->unique()->count();
        $periodSummary = [
            'range' => ($chartKeys ? \Illuminate\Support\Carbon::createFromFormat('Y-m', $chartKeys[0])->format('M Y') : '')
                . ' – ' . ($chartKeys ? \Illuminate\Support\Carbon::createFromFormat('Y-m', end($chartKeys))->format('M Y') : ''),
            'total_confirmed_shoots' => $periodSummaryShoots,
            'crew_assigned_shoots' => $periodSummaryAssigned,
            'shoots_needing_crew' => max(0, $periodSummaryShoots - $periodSummaryAssigned),
            'unique_crew_members' => $chartCrewLines->pluck('crew_id')->unique()->count(),
            'total_shoot_days' => (float) $chartCrewLines->sum('hours_worked'),
            'attendance_exceptions' => (int) $chartNoShowDays->sum(),
        ];

        $prevPeriod = ReportPeriod::previous($period);
        $crewDeltas = ['spend' => null, 'crew_booked' => null];
        if ($prevPeriod) {
            $prev = $this->spendAndCrewBooked($prevPeriod['from'], $prevPeriod['to']);
            $crewDeltas['spend'] = ReportPeriod::delta($spendTotal, $prev['spend']);
            $crewDeltas['crew_booked'] = ReportPeriod::delta($crewBooked, $prev['crew_booked']);
        }

        return view('crew-data', [
            'period' => $period, 'search' => $search,
            'roleFilter' => $roleFilter, 'engagementFilter' => $engagementFilter,
            'roleOptions' => $roleOptions, 'engagementOptions' => $engagementOptions,
            'byPerson' => $byPerson, 'byRole' => $byRole, 'monthly' => $monthly,
            'crewDeltas' => $crewDeltas, 'needsRoleCount' => $needsRoleCount,
            'periodSummary' => $periodSummary,
            'kpis' => [
                'spend' => $spendTotal,
                'shoots' => $bookingIds->count(),
                'crew_booked' => $crewBooked,
                'crew_assigned_shoots' => $crewAssignedShoots,
                'shoots_needing_crew' => $shootsNeedingCrew,
                'avg_per_shoot' => $crewAssignedShoots ? round($spendTotal / $crewAssignedShoots, 2) : 0.0,
                'chart_months' => $chartMonths,
            ],
        ]);
    }

    private function crewByPersonAndRole(array $period, string $search, string $roleFilter = '', string $engagementFilter = ''): array
    {
        $confirmedCes = CeAnalytics::confirmedCes($period['from'], $period['to']);
        $bookingIds = $confirmedCes->pluck('booking_id');

        $crewLines = DB::table('booking_crew as bc')
            ->join('crew_members as cm', 'bc.crew_id', '=', 'cm.crew_id')
            ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
            ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
            ->whereIn('bc.booking_id', $bookingIds)
            ->select('bc.booking_id', 'bc.crew_id', 'bc.hours_worked', 'bc.rate_used',
                'cm.first_name', 'cm.last_name', 'cm.employment_type', 'cp.position_name', 'b.shoot_date_end')
            ->get();

        $needsRoleCount = $crewLines->whereNull('position_name')->pluck('crew_id')->unique()->count();

        $noShowDays = DB::table('crew_attendance')
            ->whereIn('booking_id', $bookingIds)
            ->whereIn('status', ['no_show', 'back_out'])
            ->select('booking_id', 'crew_id')
            ->get()
            ->countBy(fn ($r) => $r->booking_id . '-' . $r->crew_id);

        foreach ($crewLines as $line) {
            $bad = $noShowDays->get($line->booking_id . '-' . $line->crew_id, 0);
            $line->paid_days = max(0.0, (float) $line->hours_worked - $bad);
            $line->no_shows = $bad;
        }

        $byPerson = $crewLines->groupBy('crew_id')->map(function ($rows) {
            $first = $rows->first();

            return (object) [
                'name' => trim($first->first_name . ' ' . $first->last_name),
                'positions' => $rows->pluck('position_name')->filter()->unique()->implode(', '),
                'employment_type' => $first->employment_type,
                'shoots' => $rows->pluck('booking_id')->unique()->count(),
                'days' => (float) $rows->sum('paid_days'),
                'paid' => (float) $rows->sum(fn ($r) => $r->rate_used * $r->paid_days),
                'no_shows' => (int) $rows->sum('no_shows'),
                'last_worked' => $rows->pluck('shoot_date_end')->filter()->max(),
            ];
        })->sortByDesc('paid')->values();

        $byRole = $crewLines->whereNotNull('position_name')->groupBy('position_name')->map(function ($rows, $role) {
            return (object) [
                'role' => $role,
                'shoots' => $rows->pluck('booking_id')->unique()->count(),
                'headcount' => $rows->pluck('crew_id')->unique()->count(),
                'paid' => (float) $rows->sum(fn ($r) => $r->rate_used * $r->paid_days),
            ];
        })->sortByDesc('paid')->values();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $byPerson = $byPerson->filter(fn ($p) => str_contains(mb_strtolower($p->name), $needle)
                || str_contains(mb_strtolower($p->positions), $needle))->values();
            $byRole = $byRole->filter(fn ($r) => str_contains(mb_strtolower($r->role), $needle))->values();
        }
        if ($roleFilter !== '') {
            $byPerson = $byPerson->filter(fn ($p) => str_contains($p->positions, $roleFilter))->values();
            $byRole = $byRole->filter(fn ($r) => $r->role === $roleFilter)->values();
        }
        if ($engagementFilter !== '') {
            $byPerson = $byPerson->filter(fn ($p) => $p->employment_type === $engagementFilter)->values();
        }

        return [$byPerson, $byRole, $crewLines, $bookingIds, $needsRoleCount];
    }

    private function export(Request $request): StreamedResponse|Response
    {
        $period = ReportPeriod::resolve($request);
        $search = trim((string) $request->query('q', ''));
        $roleFilter = trim((string) $request->query('role', ''));
        $engagementFilter = trim((string) $request->query('engagement', ''));
        $type = $request->query('export', 'by_person');

        [$byPerson, $byRole] = $this->crewByPersonAndRole($period, $search, $roleFilter, $engagementFilter);

        $userId = Auth::id();
        $generatedBy = $userId ? trim((string) DB::table('users')->where('user_id', $userId)
            ->selectRaw("CONCAT(first_name,' ',last_name) AS n")->value('n')) : null;
        $infoRows = [
            ['Applied Period Filter', $period['label'] ?? 'All time'],
            ['Role Filter', $roleFilter ?: 'All Roles'],
            ['Engagement Type Filter', $engagementFilter ?: 'All Engagement Types'],
            ['Search', $search ?: '—'],
            ['Crew Cost Source', 'Confirmed cost estimates\' bookings, live booking_crew assignments minus logged no-show/back-out attendance days'],
            ['Generated', now()->format('M j, Y g:i A')],
            ['Generated By', $generatedBy ?: 'Unknown'],
        ];

        if ($type === 'by_role') {
            $headers = ['Production Role', 'Shoots', 'Unique Crew', 'Confirmed Crew Cost'];
            $rows = $byRole->map(fn ($r) => [
                $r->role, $r->shoots, $r->headcount, '₱' . number_format($r->paid, 2),
            ])->all();
            $title = 'Crew Analytics — By Role';
            $sectionTitle = 'Crew by Role';
            $filename = 'crew-analytics-by-role';
        } else {
            $headers = ['Crew Member', 'Positions', 'Shoots', 'Shoot Days', 'Confirmed Crew Cost', 'Attendance Exceptions', 'Last Worked'];
            $rows = $byPerson->map(fn ($p) => [
                $p->name, $p->positions ?: 'Unassigned', $p->shoots, $p->days,
                '₱' . number_format($p->paid, 2), $p->no_shows,
                $p->last_worked ? \Illuminate\Support\Carbon::parse($p->last_worked)->format('M j, Y') : '—',
            ])->all();
            $title = 'Crew Analytics — By Crew Member';
            $sectionTitle = 'Crew Members';
            $filename = 'crew-analytics-by-person';
        }

        return DataExporter::respondSections($request->query('format', 'csv'), $title, [
            ['title' => 'Report Info', 'rows' => $infoRows],
            ['title' => $sectionTitle, 'headers' => $headers, 'rows' => $rows],
        ], $filename);
    }

    private function spendAndCrewBooked(string $from, string $to): array
    {
        $bookingIds = CeAnalytics::confirmedCes($from, $to)->pluck('booking_id');

        $lines = DB::table('booking_crew')
            ->whereIn('booking_id', $bookingIds)
            ->select('booking_id', 'crew_id', 'hours_worked', 'rate_used')
            ->get();

        $noShowDays = DB::table('crew_attendance')
            ->whereIn('booking_id', $bookingIds)
            ->whereIn('status', ['no_show', 'back_out'])
            ->select('booking_id', 'crew_id')
            ->get()
            ->countBy(fn ($r) => $r->booking_id . '-' . $r->crew_id);

        foreach ($lines as $line) {
            $bad = $noShowDays->get($line->booking_id . '-' . $line->crew_id, 0);
            $line->paid_days = max(0.0, (float) $line->hours_worked - $bad);
        }

        return [
            'spend' => (float) $lines->sum(fn ($r) => $r->rate_used * $r->paid_days),
            'crew_booked' => $lines->pluck('crew_id')->unique()->count(),
        ];
    }
}
