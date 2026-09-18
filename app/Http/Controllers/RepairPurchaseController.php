<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\DataExporter;
use App\Support\PageActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RepairPurchaseController extends Controller
{
    private array $typeBadge = ['repair' => 'badge-orange', 'purchase' => 'badge-blue'];

    private array $typeLabel = ['repair' => 'Repair', 'purchase' => 'Purchase'];

    private array $statusBadge = [
        'requested' => 'badge-gray', 'approved' => 'badge-blue', 'in_progress' => 'badge-yellow',
        'completed' => 'badge-green', 'cancelled' => 'badge-red',
    ];

    private array $statusLabel = [
        'requested' => 'Requested', 'approved' => 'Approved', 'in_progress' => 'In Progress',
        'completed' => 'Completed', 'cancelled' => 'Cancelled',
    ];

    // The same five statuses, worded the way the team talks about each kind of job (Part 15).
    // The stored values never change — only how they read.
    private array $statusLabelByType = [
        'repair' => [
            'requested' => 'For repair', 'approved' => 'Approved', 'in_progress' => 'In repair',
            'completed' => 'Repaired', 'cancelled' => 'Cancelled',
        ],
        'purchase' => [
            'requested' => 'Requested', 'approved' => 'Approved', 'in_progress' => 'Ordered',
            'completed' => 'Received', 'cancelled' => 'Cancelled',
        ],
    ];

    /** Completed tickets are kept for two years, then cleared — same self-healing sweep as Reminders. */
    private const KEEP_COMPLETED_YEARS = 2;

    private array $priorityBadge = ['low' => 'badge-gray', 'normal' => 'badge-blue', 'high' => 'badge-orange', 'urgent' => 'badge-red'];

    public function index(Request $request): View|StreamedResponse|Response
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $canPost = in_array($role, config('filmspec.all_staff'), true);
        $canManage = in_array($role, config('filmspec.manage_roles'), true);

        // Opportunistic retention sweep — no cron needed, same approach as Reminders' 45-day
        // clear. Completed tickets older than two years go; anything still open is untouched.
        DB::table('repair_purchase_tickets')
            ->where('status', 'completed')
            ->whereNotNull('completed_date')
            ->where('completed_date', '<', now()->subYears(self::KEEP_COMPLETED_YEARS)->toDateString())
            ->delete();

        $msg = null;
        if ($request->isMethod('post') && $canPost) {
            $msg = $this->handleAction($request, $user->user_id, $canManage);
        }

        if ($request->filled('export')) {
            return $this->export($request);
        }

        $tab = $request->query('tab', 'all');
        $typeFilter = $request->query('type', '');
        $search = $request->query('q', '');
        $page = max(1, (int) $request->query('p', 1));
        $perPage = 20;

        $query = $this->filteredQuery($request);

        $total = (clone $query)->count('t.ticket_id');
        $pages = max(1, (int) ceil($total / $perPage));

        $tickets = (clone $query)
            ->select('t.*', 'e.equipment_name', 'e.brand', 'e.serial_number as equip_serial')
            ->orderByDesc('t.created_at')
            ->forPage($page, $perPage)
            ->get();

        $tabCounts = [
            'all' => (int) DB::table('repair_purchase_tickets')->count(),
            'requested' => (int) DB::table('repair_purchase_tickets')->where('status', 'requested')->count(),
            'approved' => (int) DB::table('repair_purchase_tickets')->where('status', 'approved')->count(),
            'in_progress' => (int) DB::table('repair_purchase_tickets')->where('status', 'in_progress')->count(),
            'completed' => (int) DB::table('repair_purchase_tickets')->where('status', 'completed')->count(),
            'cancelled' => (int) DB::table('repair_purchase_tickets')->where('status', 'cancelled')->count(),
        ];

        $kpis = [
            'open' => (int) DB::table('repair_purchase_tickets')->whereNotIn('status', ['completed', 'cancelled'])->count(),
            'overdue' => (int) DB::table('repair_purchase_tickets')
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->whereNotNull('target_date')
                ->where('target_date', '<', now()->toDateString())
                ->count(),
            'est_outstanding' => (float) DB::table('repair_purchase_tickets')
                ->whereNotIn('status', ['completed', 'cancelled'])
                ->sum('estimated_cost'),
            'completed_this_month' => (int) DB::table('repair_purchase_tickets')
                ->where('status', 'completed')
                ->whereYear('completed_date', now()->year)
                ->whereMonth('completed_date', now()->month)
                ->count(),
        ];

        $allEquipment = DB::table('equipment')
            ->where('condition_status', '!=', 'retired')
            ->orderBy('equipment_name')
            ->select('equipment_id', 'equipment_name', 'brand', 'serial_number')
            ->get();

        // Threads for the tickets on this page only — one query, grouped.
        $messages = $tickets->isEmpty() ? collect() : DB::table('repair_purchase_messages as m')
            ->leftJoin('users as u', 'm.user_id', '=', 'u.user_id')
            ->whereIn('m.ticket_id', $tickets->pluck('ticket_id'))
            ->orderBy('m.created_at')
            ->select('m.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS author"))
            ->get()->groupBy('ticket_id');

        return view('repair-purchase', [
            'messages' => $messages,
            'statusLabelByType' => $this->statusLabelByType,
            'pageActivity' => PageActivity::forModule('repair_purchase'),
            'activityModule' => 'repair_purchase',
            'accessLog' => PageActivity::recentAccess(),
            'msg' => $msg, 'canPost' => $canPost, 'canManage' => $canManage,
            'tab' => $tab, 'typeFilter' => $typeFilter, 'search' => $search,
            'page' => $page, 'pages' => $pages,
            'tickets' => $tickets, 'tabCounts' => $tabCounts, 'kpis' => $kpis,
            'allEquipment' => $allEquipment,
            'typeBadge' => $this->typeBadge, 'typeLabel' => $this->typeLabel,
            'statusBadge' => $this->statusBadge, 'statusLabel' => $this->statusLabel,
            'priorityBadge' => $this->priorityBadge,
        ]);
    }

    /** Same filters index() applies, shared with export() so the two can never drift apart. */
    private function filteredQuery(Request $request)
    {
        $tab = $request->query('tab', 'all');
        $typeFilter = $request->query('type', '');
        $search = $request->query('q', '');

        $query = DB::table('repair_purchase_tickets as t')
            ->leftJoin('equipment as e', 't.equipment_id', '=', 'e.equipment_id');
        if ($tab !== 'all') {
            $query->where('t.status', $tab);
        }
        if ($typeFilter) {
            $query->where('t.type', $typeFilter);
        }
        if ($search) {
            $query->where(function ($w) use ($search) {
                $w->where('t.ticket_number', 'like', "%$search%")
                    ->orWhere('t.title', 'like', "%$search%")
                    ->orWhere('t.vendor_supplier', 'like', "%$search%");
            });
        }

        return $query;
    }

    /** Export ▾ — reuses filteredQuery() unbounded (no page/perPage) so it always matches what's on screen. */
    private function export(Request $request): StreamedResponse|Response
    {
        $headers = ['Ticket #', 'Type', 'Title', 'Equipment', 'Status', 'Priority', 'Est. Cost', 'Actual Cost', 'Target Date'];

        $rows = $this->filteredQuery($request)
            ->select('t.ticket_number', 't.type', 't.title', 'e.equipment_name', 't.status', 't.priority',
                't.estimated_cost', 't.actual_cost', 't.target_date')
            ->orderByDesc('t.created_at')
            ->get()
            ->map(fn ($t) => [
                $t->ticket_number,
                $this->typeLabel[$t->type] ?? ucfirst($t->type),
                $t->title,
                $t->equipment_name ?: '—',
                $this->statusLabelByType[$t->type][$t->status] ?? ($this->statusLabel[$t->status] ?? ucfirst($t->status)),
                ucfirst($t->priority),
                $t->estimated_cost !== null ? '₱' . number_format((float) $t->estimated_cost, 2) : '—',
                $t->actual_cost !== null ? '₱' . number_format((float) $t->actual_cost, 2) : '—',
                $t->target_date ? Carbon::parse($t->target_date)->format('M j, Y') : '—',
            ])
            ->all();

        return DataExporter::respond($request->query('export'), 'Repair / Purchase', $headers, $rows, 'repair-purchase-export');
    }

    private function generateTicketNumber(): string
    {
        $year = now()->year;
        $count = (int) DB::table('repair_purchase_tickets')->whereYear('created_at', $year)->count();

        return 'RP-' . $year . '-' . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    private function handleAction(Request $request, int $uid, bool $canManage): ?array
    {
        $action = $request->input('action', '');

        if ($action === 'add_ticket') {
            $type = $request->input('type') === 'purchase' ? 'purchase' : 'repair';
            $title = trim((string) $request->input('title', ''));
            $reqDate = $request->input('requested_date', '');
            if ($title === '' || $reqDate === '') {
                return ['type' => 'danger', 'text' => 'Title and requested date are required.'];
            }

            $eid = (int) $request->input('equipment_id', 0);
            $ticketNo = $this->generateTicketNumber();
            $id = DB::table('repair_purchase_tickets')->insertGetId([
                'ticket_number' => $ticketNo,
                'type' => $type,
                'title' => $title,
                'equipment_id' => ($type === 'repair' && $eid) ? $eid : null,
                'status' => 'requested',
                'priority' => $request->input('priority', 'normal'),
                'vendor_supplier' => $request->input('vendor_supplier') ?: null,
                'estimated_cost' => $request->input('estimated_cost') !== null && $request->input('estimated_cost') !== '' ? (float) $request->input('estimated_cost') : null,
                'requested_date' => $reqDate,
                'target_date' => $request->input('target_date') ?: null,
                'person_in_charge' => $request->input('person_in_charge') ?: null,
                'note' => $request->input('note') ?: null,
                'created_by' => $uid,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'ticket_id');

            ActivityLog::record($uid, 'create', 'repair_purchase', "Created ticket <strong>$ticketNo</strong> \"$title\".", $id);

            return ['type' => 'success', 'text' => "Ticket <strong>$ticketNo</strong> created."];
        }

        if ($action === 'update_ticket') {
            $id = (int) $request->input('ticket_id');
            $ticket = DB::table('repair_purchase_tickets')->where('ticket_id', $id)->first();
            if (! $ticket) {
                return ['type' => 'danger', 'text' => 'Ticket not found.'];
            }

            $title = trim((string) $request->input('title', ''));
            if ($title === '') {
                return ['type' => 'danger', 'text' => 'Title is required.'];
            }

            $type = $request->input('type') === 'purchase' ? 'purchase' : 'repair';
            $eid = (int) $request->input('equipment_id', 0);

            DB::table('repair_purchase_tickets')->where('ticket_id', $id)->update([
                'type' => $type,
                'title' => $title,
                'equipment_id' => ($type === 'repair' && $eid) ? $eid : null,
                'serial_no' => trim((string) $request->input('serial_no', '')) ?: null,
                'priority' => $request->input('priority', $ticket->priority),
                'vendor_supplier' => trim((string) $request->input('vendor_supplier', '')) ?: null,
                'estimated_cost' => $request->input('estimated_cost') !== null && $request->input('estimated_cost') !== ''
                    ? (float) $request->input('estimated_cost') : null,
                'requested_date' => $request->input('requested_date') ?: $ticket->requested_date,
                'target_date' => $request->input('target_date') ?: null,
                'person_in_charge' => trim((string) $request->input('person_in_charge', '')) ?: null,
                'reported_by' => trim((string) $request->input('reported_by', '')) ?: null,
                'note' => trim((string) $request->input('note', '')) ?: null,
                'updated_at' => now(),
            ]);

            ActivityLog::record($uid, 'update', 'repair_purchase', "Edited ticket <strong>{$ticket->ticket_number}</strong>.", $id);

            return ['type' => 'success', 'text' => 'Ticket updated.'];
        }

        if ($action === 'post_ticket_message') {
            $id = (int) $request->input('ticket_id');
            $ticket = DB::table('repair_purchase_tickets')->where('ticket_id', $id)->first();
            if (! $ticket) {
                return ['type' => 'danger', 'text' => 'Ticket not found.'];
            }

            $body = trim((string) $request->input('body', ''));
            if ($body === '') {
                return ['type' => 'danger', 'text' => 'Write a message before posting.'];
            }

            DB::table('repair_purchase_messages')->insert([
                'ticket_id' => $id, 'user_id' => $uid, 'body' => $body, 'created_at' => now(),
            ]);
            ActivityLog::record($uid, 'create', 'repair_purchase', "Posted a message on <strong>{$ticket->ticket_number}</strong>.", $id);

            return ['type' => 'success', 'text' => 'Message posted.'];
        }

        if ($action === 'update_ticket_status') {
            $id = (int) $request->input('ticket_id');
            $ticket = DB::table('repair_purchase_tickets')->where('ticket_id', $id)->first();
            if (! $ticket) {
                return ['type' => 'danger', 'text' => 'Ticket not found.'];
            }

            $status = $request->input('status', $ticket->status);
            if (! array_key_exists($status, $this->statusLabel)) {
                return ['type' => 'danger', 'text' => 'Invalid status.'];
            }

            $update = ['status' => $status, 'updated_at' => now()];
            if ($status === 'completed') {
                $update['completed_date'] = now()->toDateString();
                $actual = $request->input('actual_cost');
                if ($actual !== null && $actual !== '') {
                    $update['actual_cost'] = (float) $actual;
                }
            } else {
                $update['completed_date'] = null;
            }
            if ($request->filled('note')) {
                $update['note'] = trim((string) $request->input('note'));
            }

            DB::table('repair_purchase_tickets')->where('ticket_id', $id)->update($update);
            ActivityLog::record($uid, 'update', 'repair_purchase', "Ticket <strong>{$ticket->ticket_number}</strong> status set to {$this->statusLabel[$status]}.", $id);

            return ['type' => 'success', 'text' => 'Ticket updated.'];
        }

        if ($action === 'delete_ticket') {
            if (! $canManage) {
                return ['type' => 'danger', 'text' => 'You do not have permission to delete tickets.'];
            }
            $id = (int) $request->input('ticket_id');
            $ticket = DB::table('repair_purchase_tickets')->where('ticket_id', $id)->first();
            if (! $ticket) {
                return ['type' => 'danger', 'text' => 'Ticket not found.'];
            }

            DB::table('repair_purchase_tickets')->where('ticket_id', $id)->delete();
            ActivityLog::record($uid, 'delete', 'repair_purchase', "Deleted ticket <strong>{$ticket->ticket_number}</strong>.", $id);

            return ['type' => 'success', 'text' => 'Ticket deleted.'];
        }

        return null;
    }
}
