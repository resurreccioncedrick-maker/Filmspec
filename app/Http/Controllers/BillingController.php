<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\DataExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    private array $soaBadge = ['draft' => 'badge-gray', 'issued' => 'badge-blue', 'paid' => 'badge-green', 'overdue' => 'badge-red'];

    private array $rcBadge = ['official_receipt' => 'badge-green', 'acknowledgement_receipt' => 'badge-blue'];

    private array $pmBadge = ['bank_transfer' => 'badge-blue', 'gcash' => 'badge-purple', 'cash' => 'badge-green'];

    private array $ptBadge = ['downpayment' => 'badge-orange', 'progress' => 'badge-blue', 'final' => 'badge-green'];

    private array $payBadge = ['unpaid' => 'badge-yellow', 'partial' => 'badge-orange', 'paid' => 'badge-green', 'overdue' => 'badge-red', 'refunded' => 'badge-purple', 'cancelled' => 'badge-gray'];

    private array $payLabel = ['unpaid' => 'Unpaid', 'partial' => 'Partially Paid', 'paid' => 'Fully Paid', 'overdue' => 'Overdue', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled'];

    public function index(Request $request): View|StreamedResponse|Response
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $canRecord = in_array($role, ['super_admin', 'admin', 'operations_manager', 'accounting'], true);

        // Auto-mark overdue SOAs and bookings
        DB::table('statement_of_accounts')
            ->where('due_date', '<', now()->toDateString())
            ->whereIn('status', ['issued', 'draft'])
            ->where('balance', '>', 0)
            ->update(['status' => 'overdue']);
        DB::statement("UPDATE bookings b
            JOIN statement_of_accounts s ON b.booking_id = s.booking_id
            SET b.payment_status = 'overdue'
            WHERE s.status = 'overdue' AND b.payment_status IN ('unpaid','partial')");

        $msg = null;
        if ($request->isMethod('post') && $canRecord) {
            $msg = $this->handleAction($request);
        }

        if ($request->filled('export')) {
            return $this->export($request);
        }

        $tab = $request->query('tab', 'payments');

        $search = $request->query('q', '');
        $payTypeFilter = $request->query('ptype', '');
        $payMethFilter = $request->query('pmethod', '');
        $rcTypeFilter = $request->query('rctype', '');
        $page = max(1, (int) $request->query('p', 1));
        $perPage = 15;

        $pQuery = $this->filteredPaymentsQuery($request);

        $totalPay = (clone $pQuery)->count();
        $payPages = max(1, (int) ceil($totalPay / $perPage));

        $payments = (clone $pQuery)
            // payment_id, not created_at — demo/seed data backdates created_at to line up with
            // payment_date rather than real insertion order (same issue the Bookings list had),
            // so a freshly recorded payment could sort below older seeded rows with a fabricated
            // later timestamp. payment_id always reflects true insertion order.
            ->orderByDesc('p.payment_id')
            ->select('p.*', 'b.booking_reference', 'b.project_title', 'b.final_amount', 'c.contact_person', 'c.company_name')
            ->forPage($page, $perPage)
            ->get();

        $soaSearch = $request->query('q_soa', '');
        $soaStatus = $request->query('sstatus', '');
        $soQuery = $this->filteredSoaQuery($request);
        $soaList = $soQuery
            ->orderByDesc('s.created_at')
            ->select('s.*', 'b.booking_reference', 'b.project_title', 'c.contact_person', 'c.company_name')
            ->selectRaw("CONCAT(u.first_name,' ',u.last_name) AS prepared_by_name")
            ->limit(100)
            ->get();

        $pendingPayBkgs = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->leftJoin('payments as p', 'b.booking_id', '=', 'p.booking_id')
            ->whereIn('b.booking_status', ['confirmed', 'ongoing', 'completed'])
            ->where('b.payment_status', '!=', 'paid')
            ->where(function ($w) {
                $w->whereNull('b.approval_status')->orWhere('b.approval_status', 'approved');
            })
            ->groupBy('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.final_amount', 'b.payment_status', 'c.contact_person', 'c.company_name', 'c.is_vat_registered', 'c.client_type')
            ->orderByDesc('b.shoot_date_start')
            ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.final_amount', 'b.payment_status', 'c.contact_person', 'c.company_name', 'c.is_vat_registered', 'c.client_type')
            ->selectRaw('COALESCE(SUM(p.amount),0) AS paid_so_far')
            ->get();

        $dueSoonCount = (int) DB::table('statement_of_accounts')
            ->where('status', 'issued')->where('balance', '>', 0)
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays(7)->toDateString()])
            ->count();

        $odSearch = $request->query('q_od', '');
        $odQuery = $this->filteredOverdueQuery($request);
        $overdueDetailList = $odQuery
            ->orderBy('s.due_date')
            ->select('s.*', 'b.booking_reference', 'b.project_title', 'b.payment_status', 'c.contact_person', 'c.company_name', 'c.phone as client_phone', 'c.email as client_email')
            ->selectRaw('DATEDIFF(CURDATE(), s.due_date) AS days_overdue')
            ->get();

        $ccSearch = $request->query('q_cc', '');
        $ccType = $request->query('ctype', '');
        $ccQuery = $this->filteredCancellationsQuery($request);
        $cancellationCharges = $ccQuery
            ->orderByDesc('bc.approved_at')
            ->select('bc.*', 'b.booking_reference', 'b.project_title', 'c.contact_person', 'c.company_name')
            ->selectRaw("CONCAT(u.first_name,' ',u.last_name) AS approved_by_name")
            ->limit(100)
            ->get();

        $totalCollected = (float) DB::table('payments')->sum('amount');
        $monthCollected = (float) DB::table('payments')
            ->whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year)
            ->sum('amount');
        $outstanding = (float) DB::table('statement_of_accounts')->where('status', '!=', 'paid')->sum('balance');
        // Reuses filteredOverdueQuery()'s own join+where (unfiltered, so an active search on the
        // Overdue tab doesn't shrink this badge) instead of a separate hand-copied predicate —
        // the two must be structurally identical, not just textually similar, or a data anomaly
        // the join quietly drops (e.g. a statement_of_accounts row whose booking_id no longer
        // exists) inflates this count while never actually appearing in the list below.
        $overdueCount = (int) $this->filteredOverdueQuery(new Request())->count();

        // Relocated from the Cost Estimate editor — accounting/admin approve discounts here.
        $pendingDiscounts = DB::table('booking_discounts as bd')
            ->join('bookings as b', 'bd.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->leftJoin('users as u', 'bd.proposed_by', '=', 'u.user_id')
            ->where('bd.status', 'pending')
            ->orderByDesc('bd.created_at')
            ->select('bd.*', 'b.booking_reference', 'b.project_title', 'c.contact_person', 'c.company_name')
            ->selectRaw("CONCAT(u.first_name,' ',u.last_name) AS proposed_by_name")
            ->get();
        $discountHistory = DB::table('booking_discounts as bd')
            ->join('bookings as b', 'bd.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->leftJoin('users as u', 'bd.proposed_by', '=', 'u.user_id')
            ->leftJoin('users as u2', 'bd.approved_by', '=', 'u2.user_id')
            ->where('bd.status', '!=', 'pending')
            ->orderByDesc('bd.approved_at')
            ->select('bd.*', 'b.booking_reference', 'b.project_title', 'c.contact_person', 'c.company_name')
            ->selectRaw("CONCAT(u.first_name,' ',u.last_name) AS proposed_by_name")
            ->selectRaw("CONCAT(u2.first_name,' ',u2.last_name) AS reviewed_by_name")
            ->limit(25)
            ->get();

        // Client-submitted payment claims (ClientBookingDetailController::handleSubmitPayment())
        // — a client's own "Pay" submission never touches `payments` directly; it sits here
        // until accounting/admin approves it, which is the only thing that actually creates the
        // real payments row.
        $pendingPaymentSubmissions = DB::table('client_payment_submissions as cps')
            ->join('bookings as b', 'cps.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->leftJoin('users as u', 'cps.submitted_by', '=', 'u.user_id')
            ->where('cps.status', 'pending')
            ->orderByDesc('cps.submission_id')
            ->select('cps.*', 'b.booking_reference', 'b.project_title', 'b.final_amount', 'c.contact_person', 'c.company_name')
            ->selectRaw("CONCAT(u.first_name,' ',u.last_name) AS submitted_by_name")
            ->get();
        $paymentSubmissionHistory = DB::table('client_payment_submissions as cps')
            ->join('bookings as b', 'cps.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->leftJoin('users as u', 'cps.submitted_by', '=', 'u.user_id')
            ->leftJoin('users as u2', 'cps.reviewed_by', '=', 'u2.user_id')
            ->where('cps.status', '!=', 'pending')
            ->orderByDesc('cps.reviewed_at')
            ->select('cps.*', 'b.booking_reference', 'b.project_title', 'c.contact_person', 'c.company_name')
            ->selectRaw("CONCAT(u.first_name,' ',u.last_name) AS submitted_by_name")
            ->selectRaw("CONCAT(u2.first_name,' ',u2.last_name) AS reviewed_by_name")
            ->limit(25)
            ->get();

        return view('billing', [
            'msg' => $msg, 'canRecord' => $canRecord, 'tab' => $tab,
            'pendingDiscounts' => $pendingDiscounts, 'discountHistory' => $discountHistory,
            'pendingPaymentSubmissions' => $pendingPaymentSubmissions, 'paymentSubmissionHistory' => $paymentSubmissionHistory,
            'payments' => $payments, 'totalPay' => $totalPay, 'payPages' => $payPages, 'page' => $page,
            'search' => $search, 'payTypeFilter' => $payTypeFilter, 'payMethFilter' => $payMethFilter, 'rcTypeFilter' => $rcTypeFilter,
            'soaList' => $soaList, 'soaSearch' => $soaSearch, 'soaStatus' => $soaStatus,
            'pendingPayBkgs' => $pendingPayBkgs, 'dueSoonCount' => $dueSoonCount,
            'overdueDetailList' => $overdueDetailList, 'odSearch' => $odSearch,
            'cancellationCharges' => $cancellationCharges, 'ccSearch' => $ccSearch, 'ccType' => $ccType,
            'totalCollected' => $totalCollected, 'monthCollected' => $monthCollected,
            'outstanding' => $outstanding, 'overdueCount' => $overdueCount,
            'soaBadge' => $this->soaBadge, 'rcBadge' => $this->rcBadge, 'pmBadge' => $this->pmBadge,
            'ptBadge' => $this->ptBadge, 'payBadge' => $this->payBadge, 'payLabel' => $this->payLabel,
        ]);
    }

    /** Same filters index() applies to the Payments tab, shared with export(). */
    private function filteredPaymentsQuery(Request $request)
    {
        $search = $request->query('q', '');
        $payTypeFilter = $request->query('ptype', '');
        $payMethFilter = $request->query('pmethod', '');
        $rcTypeFilter = $request->query('rctype', '');

        $pQuery = DB::table('payments as p')
            ->join('bookings as b', 'p.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id');
        if ($search) {
            $pQuery->where(function ($w) use ($search) {
                $w->where('b.booking_reference', 'like', "%$search%")
                    ->orWhere('c.contact_person', 'like', "%$search%")
                    ->orWhere('c.company_name', 'like', "%$search%")
                    ->orWhere('p.receipt_number', 'like', "%$search%");
            });
        }
        if ($payTypeFilter) $pQuery->where('p.payment_type', $payTypeFilter);
        if ($payMethFilter) $pQuery->where('p.payment_method', $payMethFilter);
        if ($rcTypeFilter) $pQuery->where('p.receipt_type', $rcTypeFilter);

        return $pQuery;
    }

    /** Same filters index() applies to the Final Billing (SOA) tab, shared with export(). */
    private function filteredSoaQuery(Request $request)
    {
        $soaSearch = $request->query('q_soa', '');
        $soaStatus = $request->query('sstatus', '');

        $soQuery = DB::table('statement_of_accounts as s')
            ->join('bookings as b', 's.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->leftJoin('users as u', 's.prepared_by', '=', 'u.user_id');
        if ($soaSearch) {
            $soQuery->where(function ($w) use ($soaSearch) {
                $w->where('b.booking_reference', 'like', "%$soaSearch%")
                    ->orWhere('c.company_name', 'like', "%$soaSearch%")
                    ->orWhere('c.contact_person', 'like', "%$soaSearch%")
                    ->orWhere('s.soa_reference', 'like', "%$soaSearch%");
            });
        }
        if ($soaStatus) $soQuery->where('s.status', $soaStatus);

        return $soQuery;
    }

    /** Same filter index() applies to the Overdue tab, shared with export(). */
    private function filteredOverdueQuery(Request $request)
    {
        $odSearch = $request->query('q_od', '');

        $odQuery = DB::table('statement_of_accounts as s')
            ->join('bookings as b', 's.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            // Same definition as $overdueCount above, not a literal status='overdue' match — that
            // status only gets set by index()'s own auto-mark update, so relying on it here made
            // this list depend on a side effect instead of just checking the real due date/balance.
            ->where('s.due_date', '<', now()->toDateString())
            ->where('s.status', '!=', 'paid')
            ->where('s.balance', '>', 0);
        if ($odSearch) {
            $odQuery->where(function ($w) use ($odSearch) {
                $w->where('b.booking_reference', 'like', "%$odSearch%")
                    ->orWhere('c.company_name', 'like', "%$odSearch%")
                    ->orWhere('c.contact_person', 'like', "%$odSearch%");
            });
        }

        return $odQuery;
    }

    /** Same filters index() applies to the Cancellation Charges tab, shared with export(). */
    private function filteredCancellationsQuery(Request $request)
    {
        $ccSearch = $request->query('q_cc', '');
        $ccType = $request->query('ctype', '');

        $ccQuery = DB::table('booking_cancellations as bc')
            ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->leftJoin('users as u', 'bc.approved_by', '=', 'u.user_id')
            ->where('bc.status', 'approved')->where('bc.penalty_amount', '>', 0);
        if ($ccSearch) {
            $ccQuery->where(function ($w) use ($ccSearch) {
                $w->where('b.booking_reference', 'like', "%$ccSearch%")
                    ->orWhere('c.company_name', 'like', "%$ccSearch%")
                    ->orWhere('c.contact_person', 'like', "%$ccSearch%");
            });
        }
        if ($ccType) $ccQuery->where('bc.request_type', $ccType);

        return $ccQuery;
    }

    /**
     * Export ▾ — Billing has 6 independent tabs with no shared column shape, so each gets its
     * own scoped export (?export=<tab>&format=<fmt>) instead of one combined file, mirroring
     * the Reports/Crew Data multi-dataset convention.
     */
    private function export(Request $request): StreamedResponse|Response
    {
        $type = $request->query('export', 'payments');
        $format = $request->query('format', 'csv');

        if ($type === 'soa') {
            $headers = ['SOA Ref', 'Booking', 'Client', 'Total Charges', 'Paid', 'Balance', 'Due Date', 'Status'];
            $rows = $this->filteredSoaQuery($request)->orderByDesc('s.created_at')
                ->select('s.soa_reference', 'b.booking_reference', 'c.company_name', 'c.contact_person',
                    's.total_charges', 's.total_payments', 's.balance', 's.due_date', 's.status')
                ->get()
                ->map(fn ($s) => [
                    $s->soa_reference, $s->booking_reference, $s->company_name ?: $s->contact_person,
                    '₱' . number_format((float) $s->total_charges, 2), '₱' . number_format((float) $s->total_payments, 2),
                    '₱' . number_format((float) $s->balance, 2),
                    $s->due_date ? Carbon::parse($s->due_date)->format('M j, Y') : '—',
                    ucfirst($s->status),
                ])->all();

            return DataExporter::respond($format, 'Billing — Final Billing (SOA)', $headers, $rows, 'billing-soa-export');
        }

        if ($type === 'overdue') {
            $headers = ['SOA Ref', 'Booking', 'Client', 'Balance', 'Due Date', 'Days Overdue'];
            $rows = $this->filteredOverdueQuery($request)->orderBy('s.due_date')
                ->select('s.soa_reference', 'b.booking_reference', 'c.company_name', 'c.contact_person', 's.balance', 's.due_date')
                ->selectRaw('DATEDIFF(CURDATE(), s.due_date) AS days_overdue')
                ->get()
                ->map(fn ($s) => [
                    $s->soa_reference, $s->booking_reference, $s->company_name ?: $s->contact_person,
                    '₱' . number_format((float) $s->balance, 2),
                    $s->due_date ? Carbon::parse($s->due_date)->format('M j, Y') : '—',
                    (int) $s->days_overdue,
                ])->all();

            return DataExporter::respond($format, 'Billing — Overdue', $headers, $rows, 'billing-overdue-export');
        }

        if ($type === 'cancellations') {
            $headers = ['Booking', 'Client', 'Request Type', 'Penalty Amount', 'Approved By', 'Approved At'];
            $rows = $this->filteredCancellationsQuery($request)->orderByDesc('bc.approved_at')
                ->select('b.booking_reference', 'c.company_name', 'c.contact_person', 'bc.request_type',
                    'bc.penalty_amount', 'bc.approved_at', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS approved_by_name"))
                ->get()
                ->map(fn ($c) => [
                    $c->booking_reference, $c->company_name ?: $c->contact_person,
                    ucfirst(str_replace('_', ' ', $c->request_type)),
                    '₱' . number_format((float) $c->penalty_amount, 2),
                    trim((string) $c->approved_by_name) ?: '—',
                    $c->approved_at ? Carbon::parse($c->approved_at)->format('M j, Y') : '—',
                ])->all();

            return DataExporter::respond($format, 'Billing — Cancellation Charges', $headers, $rows, 'billing-cancellations-export');
        }

        if ($type === 'unbilled') {
            $headers = ['Booking', 'Client', 'Payment Status', 'Total', 'Paid So Far'];
            $rows = DB::table('bookings as b')
                ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->leftJoin('payments as p', 'b.booking_id', '=', 'p.booking_id')
                ->whereIn('b.booking_status', ['confirmed', 'ongoing', 'completed'])
                ->where('b.payment_status', '!=', 'paid')
                ->where(function ($w) {
                    $w->whereNull('b.approval_status')->orWhere('b.approval_status', 'approved');
                })
                ->groupBy('b.booking_id', 'b.booking_reference', 'b.final_amount', 'b.payment_status', 'c.contact_person', 'c.company_name')
                ->orderByDesc('b.shoot_date_start')
                ->select('b.booking_reference', 'c.company_name', 'c.contact_person', 'b.final_amount', 'b.payment_status')
                ->selectRaw('COALESCE(SUM(p.amount),0) AS paid_so_far')
                ->get()
                ->map(fn ($b) => [
                    $b->booking_reference, $b->company_name ?: $b->contact_person,
                    $this->payLabel[$b->payment_status] ?? ucfirst($b->payment_status),
                    '₱' . number_format((float) $b->final_amount, 2), '₱' . number_format((float) $b->paid_so_far, 2),
                ])->all();

            return DataExporter::respond($format, 'Billing — Unbilled', $headers, $rows, 'billing-unbilled-export');
        }

        if ($type === 'discounts') {
            $headers = ['Booking', 'Client', 'Discount', 'Reason', 'Status', 'Proposed By', 'Reviewed By'];
            $pending = DB::table('booking_discounts as bd')
                ->join('bookings as b', 'bd.booking_id', '=', 'b.booking_id')
                ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->leftJoin('users as u', 'bd.proposed_by', '=', 'u.user_id')
                ->where('bd.status', 'pending')
                ->select('b.booking_reference', 'c.company_name', 'c.contact_person', 'bd.discount_type', 'bd.discount_value',
                    'bd.reason', 'bd.status', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS proposed_by_name"), DB::raw('NULL AS reviewed_by_name'))
                ->get();
            $history = DB::table('booking_discounts as bd')
                ->join('bookings as b', 'bd.booking_id', '=', 'b.booking_id')
                ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->leftJoin('users as u', 'bd.proposed_by', '=', 'u.user_id')
                ->leftJoin('users as u2', 'bd.approved_by', '=', 'u2.user_id')
                ->where('bd.status', '!=', 'pending')
                ->select('b.booking_reference', 'c.company_name', 'c.contact_person', 'bd.discount_type', 'bd.discount_value',
                    'bd.reason', 'bd.status', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS proposed_by_name"), DB::raw("CONCAT(u2.first_name,' ',u2.last_name) AS reviewed_by_name"))
                ->get();
            $rows = $pending->concat($history)->map(fn ($d) => [
                $d->booking_reference, $d->company_name ?: $d->contact_person,
                $d->discount_type === 'percent' ? $d->discount_value . '%' : '₱' . number_format((float) $d->discount_value, 2),
                $d->reason ?: '—', ucfirst($d->status),
                trim((string) $d->proposed_by_name) ?: '—', trim((string) $d->reviewed_by_name) ?: '—',
            ])->all();

            return DataExporter::respond($format, 'Billing — Discounts', $headers, $rows, 'billing-discounts-export');
        }

        // Default: payments
        $headers = ['Receipt #', 'Booking', 'Client', 'Type', 'Method', 'Amount', 'Date'];
        $rows = $this->filteredPaymentsQuery($request)->orderByDesc('p.payment_id')
            ->select('p.receipt_number', 'b.booking_reference', 'c.company_name', 'c.contact_person',
                'p.payment_type', 'p.payment_method', 'p.amount', 'p.payment_date')
            ->get()
            ->map(fn ($p) => [
                $p->receipt_number, $p->booking_reference, $p->company_name ?: $p->contact_person,
                ucfirst($p->payment_type), ucfirst(str_replace('_', ' ', $p->payment_method)),
                '₱' . number_format((float) $p->amount, 2),
                Carbon::parse($p->payment_date)->format('M j, Y'),
            ])->all();

        return DataExporter::respond($format, 'Billing — Payments', $headers, $rows, 'billing-payments-export');
    }

    private function handleAction(Request $request): ?array
    {
        $action = $request->input('action', '');
        $uid = $request->user()->user_id;

        if ($action === 'record_payment') {
            $bid = (int) $request->input('booking_id');
            $ptype = $request->input('payment_type');
            $pmethod = $request->input('payment_method');
            $amount = (float) $request->input('amount');
            $ref = trim($request->input('reference_number', ''));
            $bankName = trim($request->input('bank_name', ''));
            $pdate = $request->input('payment_date');
            $notes = $request->input('notes', '');

            if ($amount <= 0) {
                return ['type' => 'danger', 'text' => 'Payment amount must be greater than zero.'];
            }

            if ($pdate && $pdate < now()->toDateString()) {
                return ['type' => 'danger', 'text' => 'Payment date cannot be backdated — it must be today or later.'];
            }

            if (in_array($pmethod, ['gcash', 'bank_transfer'], true) && $ref === '') {
                return ['type' => 'danger', 'text' => 'Reference No. is required for GCash and Bank Transfer payments.'];
            }
            if ($pmethod === 'bank_transfer' && $bankName === '') {
                return ['type' => 'danger', 'text' => 'Bank / Financial Institution is required for Bank Transfer payments.'];
            }

            $proofPath = null;
            if ($request->hasFile('proof_of_payment')) {
                $file = $request->file('proof_of_payment');
                $allowedMimes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
                if ($file->isValid()) {
                    if ($file->getSize() > 10240 * 1024) {
                        return ['type' => 'danger', 'text' => 'Proof of Payment file is too large. Max 10MB.'];
                    }
                    if (! in_array($file->getMimeType(), $allowedMimes, true)) {
                        return ['type' => 'danger', 'text' => 'Proof of Payment must be a JPG, PNG, or PDF file.'];
                    }
                    $ext = strtolower($file->getClientOriginalExtension()) ?: 'bin';
                    $storedName = Str::random(40) . '.' . $ext;
                    Storage::disk('local')->putFileAs('payment_proofs', $file, $storedName);
                    $proofPath = 'payment_proofs/' . $storedName;
                }
            }

            $bookingClient = DB::table('bookings as b')
                ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->where('b.booking_id', $bid)
                ->select('c.is_vat_registered')
                ->first();
            if (! $bookingClient) {
                return ['type' => 'danger', 'text' => 'Booking not found.'];
            }
            // Document type is derived from the client's own VAT registration, not a checkbox
            // the person recording the payment could pick either way for the same client.
            $isVat = (int) $bookingClient->is_vat_registered ? 1 : 0;
            $rctype = $isVat ? 'official_receipt' : 'acknowledgement_receipt';
            $rcPrefix = $isVat ? 'OR' : 'AR';

            // The remaining-balance check and the insert both happen inside the same
            // booking-row-locked transaction — otherwise two near-simultaneous payment
            // submissions for the same booking could each read the same stale "amount paid
            // so far", both pass the "doesn't exceed the balance" check, and both insert,
            // together overpaying the booking.
            $error = DB::transaction(function () use ($rctype, $rcPrefix, $bid, $ptype, $pmethod, $amount, $ref, $bankName, $proofPath, $pdate, $uid, $isVat, $notes) {
                $bkTotal = (float) (DB::table('bookings')->where('booking_id', $bid)->lockForUpdate()->value('final_amount') ?? 0);
                $bkPaid = (float) DB::table('payments')->where('booking_id', $bid)->sum('amount');
                $remaining = round($bkTotal - $bkPaid, 2);
                if ($bkTotal > 0 && $amount > $remaining + 0.005) {
                    return ['type' => 'danger', 'text' => 'Payment of <strong>₱' . number_format($amount, 2) . '</strong> exceeds the remaining balance of <strong>₱' . number_format(max(0, $remaining), 2) . '</strong>. Please enter the correct amount.'];
                }

                $rcSeq = (int) DB::table('payments')->where('receipt_type', $rctype)->lockForUpdate()->count() + 1;
                $rcnum = $rcPrefix . '-' . str_pad((string) $rcSeq, 5, '0', STR_PAD_LEFT);

                DB::table('payments')->insert([
                    'booking_id' => $bid, 'payment_type' => $ptype, 'payment_method' => $pmethod, 'amount' => $amount,
                    'reference_number' => $ref, 'bank_name' => $bankName ?: null, 'proof_of_payment_path' => $proofPath,
                    'payment_date' => $pdate, 'received_by' => $uid, 'is_vat' => $isVat,
                    'receipt_number' => $rcnum, 'receipt_type' => $rctype, 'notes' => $notes,
                ]);

                return null;
            });

            if ($error !== null) {
                return $error;
            }

            $paid = (float) DB::table('payments')->where('booking_id', $bid)->sum('amount');
            $total = (float) DB::table('bookings')->where('booking_id', $bid)->value('final_amount');
            $currentStatus = (string) DB::table('bookings')->where('booking_id', $bid)->value('payment_status');
            if (! in_array($currentStatus, ['refunded', 'cancelled'], true)) {
                $payStatus = $paid <= 0 ? 'unpaid' : ($total > 0 && $paid >= $total ? 'paid' : 'partial');
                DB::table('bookings')->where('booking_id', $bid)->update(['payment_status' => $payStatus, 'updated_at' => now()]);
            }

            $soaExists = DB::table('statement_of_accounts')->where('booking_id', $bid)->value('soa_id');
            $balance = max(0, $total - $paid);
            $soaStatus = $balance <= 0 ? 'paid' : ($paid > 0 ? 'issued' : 'draft');
            if ($soaExists) {
                DB::table('statement_of_accounts')->where('booking_id', $bid)->update([
                    'total_payments' => $paid, 'balance' => $balance, 'status' => $soaStatus,
                ]);
            } else {
                $soaRef = 'FB-' . date('Y') . '-' . str_pad((string) $bid, 4, '0', STR_PAD_LEFT);
                // clients.payment_terms real enum is 50_downpayment/90_days/6_months/2_weeks_crew
                $payTerms2 = (string) DB::table('bookings as b')
                    ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                    ->where('b.booking_id', $bid)->value('c.payment_terms');
                $dueDays2 = $payTerms2 === '90_days' ? 90 : 7;
                DB::table('statement_of_accounts')->insert([
                    'booking_id' => $bid, 'soa_reference' => $soaRef, 'prepared_by' => $uid,
                    'total_charges' => $total, 'total_payments' => $paid, 'balance' => $balance,
                    'soa_date' => now()->toDateString(), 'due_date' => now()->addDays($dueDays2)->toDateString(), 'status' => $soaStatus,
                ]);
            }

            ActivityLog::record($uid, 'payment', 'billing', 'Payment ₱' . number_format($amount, 2) . " recorded for booking #$bid", $bid);

            return ['type' => 'success', 'text' => 'Payment recorded successfully. Amount Received: <strong>₱' . number_format($amount, 2) . '</strong>. Remaining Balance: <strong>₱' . number_format($balance, 2) . '</strong>.'];
        }

        if ($action === 'generate_soa') {
            $bid = (int) $request->input('booking_id');
            $total = (float) (DB::table('bookings')->where('booking_id', $bid)->value('final_amount') ?? 0);
            $paid = (float) DB::table('payments')->where('booking_id', $bid)->sum('amount');
            $bal = max(0, $total - $paid);
            $fbRef = 'FB-' . date('Y') . '-' . str_pad((string) $bid, 4, '0', STR_PAD_LEFT);
            $payTerms = (string) DB::table('bookings as b')
                ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->where('b.booking_id', $bid)->value('c.payment_terms');
            $dueDays = $payTerms === '90_days' ? 90 : 7;
            $exists = DB::table('statement_of_accounts')->where('booking_id', $bid)->value('soa_id');
            if (! $exists) {
                DB::table('statement_of_accounts')->insert([
                    'booking_id' => $bid, 'soa_reference' => $fbRef, 'prepared_by' => $uid,
                    'total_charges' => $total, 'total_payments' => $paid, 'balance' => $bal,
                    'soa_date' => now()->toDateString(), 'due_date' => now()->addDays($dueDays)->toDateString(), 'status' => 'draft',
                ]);
            }

            return ['type' => 'success', 'text' => "Final Billing $fbRef generated. Due in $dueDays day(s)."];
        }

        if ($action === 'approve_discount') {
            return $this->approveDiscount($request, $uid);
        }

        if ($action === 'reject_discount') {
            return $this->rejectDiscount($request, $uid);
        }

        if ($action === 'void_payment') {
            return $this->voidPayment($request, $uid);
        }

        if ($action === 'approve_payment_submission') {
            return $this->approvePaymentSubmission($request, $uid);
        }

        if ($action === 'reject_payment_submission') {
            return $this->rejectPaymentSubmission($request, $uid);
        }

        return null;
    }

    /**
     * Approving a client-submitted payment claim is the ONLY thing that turns it into a real
     * `payments` row — mirrors record_payment's receipt-numbering/VAT-derivation/balance-check/
     * SOA-update logic exactly (kept as its own copy rather than a shared extraction, so this
     * new path can't accidentally change behavior for the already-shipped direct-record flow).
     */
    private function approvePaymentSubmission(Request $request, int $uid): array
    {
        $subId = (int) $request->input('submission_id');
        $submission = DB::table('client_payment_submissions')->where('submission_id', $subId)->first();
        if (! $submission) {
            return ['type' => 'danger', 'text' => 'Payment submission not found.'];
        }
        if ($submission->status !== 'pending') {
            return ['type' => 'danger', 'text' => 'This submission has already been reviewed.'];
        }

        $bid = (int) $submission->booking_id;
        $bookingClient = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $bid)
            ->select('c.is_vat_registered')
            ->first();
        if (! $bookingClient) {
            return ['type' => 'danger', 'text' => 'Booking not found.'];
        }
        $isVat = (int) $bookingClient->is_vat_registered ? 1 : 0;
        $rctype = $isVat ? 'official_receipt' : 'acknowledgement_receipt';
        $rcPrefix = $isVat ? 'OR' : 'AR';
        $pdate = Carbon::parse($submission->created_at)->toDateString();

        $result = DB::transaction(function () use ($rctype, $rcPrefix, $bid, $submission, $pdate, $uid, $isVat, $subId) {
            $bkTotal = (float) (DB::table('bookings')->where('booking_id', $bid)->lockForUpdate()->value('final_amount') ?? 0);
            $bkPaid = (float) DB::table('payments')->where('booking_id', $bid)->sum('amount');
            $remaining = round($bkTotal - $bkPaid, 2);
            $amount = (float) $submission->amount;
            if ($bkTotal > 0 && $amount > $remaining + 0.005) {
                return ['error' => ['type' => 'danger', 'text' => 'This submission of <strong>₱' . number_format($amount, 2) . '</strong> now exceeds the remaining balance of <strong>₱' . number_format(max(0, $remaining), 2) . '</strong> — the balance may have changed since it was submitted. Reject it and ask the client to resubmit the correct amount.']];
            }

            $rcSeq = (int) DB::table('payments')->where('receipt_type', $rctype)->lockForUpdate()->count() + 1;
            $rcnum = $rcPrefix . '-' . str_pad((string) $rcSeq, 5, '0', STR_PAD_LEFT);

            $paymentId = DB::table('payments')->insertGetId([
                'booking_id' => $bid, 'payment_type' => $submission->payment_type, 'payment_method' => $submission->payment_method,
                'amount' => $amount, 'reference_number' => $submission->reference_number, 'bank_name' => $submission->bank_name,
                'proof_of_payment_path' => $submission->proof_of_payment_path,
                'payment_date' => $pdate, 'received_by' => $uid, 'is_vat' => $isVat,
                'receipt_number' => $rcnum, 'receipt_type' => $rctype,
                'notes' => trim('Client-submitted payment, approved. ' . ($submission->notes ?? '')),
            ]);

            DB::table('client_payment_submissions')->where('submission_id', $subId)->update([
                'status' => 'approved', 'reviewed_by' => $uid, 'reviewed_at' => now(), 'resulting_payment_id' => $paymentId,
            ]);

            return ['payment_id' => $paymentId, 'amount' => $amount];
        });

        if (isset($result['error'])) {
            return $result['error'];
        }

        $paid = (float) DB::table('payments')->where('booking_id', $bid)->sum('amount');
        $total = (float) DB::table('bookings')->where('booking_id', $bid)->value('final_amount');
        $currentStatus = (string) DB::table('bookings')->where('booking_id', $bid)->value('payment_status');
        if (! in_array($currentStatus, ['refunded', 'cancelled'], true)) {
            $payStatus = $paid <= 0 ? 'unpaid' : ($total > 0 && $paid >= $total ? 'paid' : 'partial');
            DB::table('bookings')->where('booking_id', $bid)->update(['payment_status' => $payStatus, 'updated_at' => now()]);
        }

        $soaExists = DB::table('statement_of_accounts')->where('booking_id', $bid)->value('soa_id');
        $balance = max(0, $total - $paid);
        $soaStatus = $balance <= 0 ? 'paid' : ($paid > 0 ? 'issued' : 'draft');
        if ($soaExists) {
            DB::table('statement_of_accounts')->where('booking_id', $bid)->update([
                'total_payments' => $paid, 'balance' => $balance, 'status' => $soaStatus,
            ]);
        } else {
            $soaRef = 'FB-' . date('Y') . '-' . str_pad((string) $bid, 4, '0', STR_PAD_LEFT);
            $payTerms2 = (string) DB::table('bookings as b')
                ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->where('b.booking_id', $bid)->value('c.payment_terms');
            $dueDays2 = $payTerms2 === '90_days' ? 90 : 7;
            DB::table('statement_of_accounts')->insert([
                'booking_id' => $bid, 'soa_reference' => $soaRef, 'prepared_by' => $uid,
                'total_charges' => $total, 'total_payments' => $paid, 'balance' => $balance,
                'soa_date' => now()->toDateString(), 'due_date' => now()->addDays($dueDays2)->toDateString(), 'status' => $soaStatus,
            ]);
        }

        ActivityLog::record($uid, 'payment', 'billing', 'Approved client-submitted payment ₱' . number_format($result['amount'], 2) . " for booking #$bid", $bid);

        return ['type' => 'success', 'text' => 'Client payment of <strong>₱' . number_format($result['amount'], 2) . '</strong> approved and recorded.'];
    }

    private function rejectPaymentSubmission(Request $request, int $uid): array
    {
        $subId = (int) $request->input('submission_id');
        $reason = trim($request->input('rejection_reason', ''));
        if ($reason === '') {
            return ['type' => 'danger', 'text' => 'A reason is required to reject a payment submission.'];
        }

        $submission = DB::table('client_payment_submissions')->where('submission_id', $subId)->first();
        if (! $submission) {
            return ['type' => 'danger', 'text' => 'Payment submission not found.'];
        }
        if ($submission->status !== 'pending') {
            return ['type' => 'danger', 'text' => 'This submission has already been reviewed.'];
        }

        DB::table('client_payment_submissions')->where('submission_id', $subId)->update([
            'status' => 'rejected', 'reviewed_by' => $uid, 'reviewed_at' => now(), 'rejection_reason' => $reason,
        ]);

        ActivityLog::record($uid, 'payment', 'billing', "Rejected client-submitted payment #$subId for booking #{$submission->booking_id}: $reason", (int) $submission->booking_id);

        return ['type' => 'success', 'text' => 'Payment submission rejected.'];
    }

    /**
     * Void/Reversal — genuinely new; no payment-deletion capability existed before this.
     * Append-only: the original row is only flagged (is_voided/voided_by/voided_at/void_reason),
     * never mutated or deleted. The actual balance correction is a separate negative-amount
     * reversing row (reversal_of_id points back at the original) so every existing
     * SUM(payments.amount) call site across the app (booking detail, billing, dashboard,
     * reports, client detail) nets out correctly with no changes needed there.
     */
    private function voidPayment(Request $request, int $uid): array
    {
        $pid = (int) $request->input('payment_id');
        $reason = trim((string) $request->input('void_reason', ''));
        if ($reason === '') {
            return ['type' => 'danger', 'text' => 'A reason is required to void a payment.'];
        }

        // Payment is re-read and locked inside the transaction (not just checked beforehand) so
        // two near-simultaneous void requests for the same payment can't both read is_voided=0
        // and both insert a reversing entry — same race class record_payment already guards
        // against for overpayment, via the same lockForUpdate()-inside-transaction pattern.
        $result = DB::transaction(function () use ($pid, $reason, $uid) {
            $payment = DB::table('payments')->where('payment_id', $pid)->lockForUpdate()->first();
            if (! $payment) {
                return ['type' => 'danger', 'text' => 'Payment not found.'];
            }
            if ($payment->is_voided) {
                return ['type' => 'danger', 'text' => 'This payment has already been voided.'];
            }
            if ((float) $payment->amount < 0) {
                return ['type' => 'danger', 'text' => 'This is itself a reversing entry and cannot be voided.'];
            }

            DB::table('payments')->where('payment_id', $pid)->update([
                'is_voided' => 1, 'voided_by' => $uid, 'voided_at' => now(), 'void_reason' => $reason,
            ]);

            DB::table('payments')->insert([
                'booking_id' => $payment->booking_id, 'payment_type' => $payment->payment_type,
                'payment_method' => $payment->payment_method, 'amount' => -1 * (float) $payment->amount,
                'reference_number' => $payment->receipt_number, 'payment_date' => now()->toDateString(),
                'received_by' => $uid, 'is_vat' => $payment->is_vat, 'receipt_number' => null,
                'receipt_type' => $payment->receipt_type, 'notes' => 'Reversal: ' . $reason,
                'reversal_of_id' => $pid,
            ]);

            $bid = $payment->booking_id;
            $paid = (float) DB::table('payments')->where('booking_id', $bid)->sum('amount');
            $total = (float) DB::table('bookings')->where('booking_id', $bid)->value('final_amount');
            $currentStatus = (string) DB::table('bookings')->where('booking_id', $bid)->value('payment_status');
            if (! in_array($currentStatus, ['refunded', 'cancelled'], true)) {
                $payStatus = $paid <= 0 ? 'unpaid' : ($total > 0 && $paid >= $total ? 'paid' : 'partial');
                DB::table('bookings')->where('booking_id', $bid)->update(['payment_status' => $payStatus, 'updated_at' => now()]);
            }

            $soaExists = DB::table('statement_of_accounts')->where('booking_id', $bid)->value('soa_id');
            if ($soaExists) {
                $balance = max(0, $total - $paid);
                $soaStatus = $balance <= 0 ? 'paid' : ($paid > 0 ? 'issued' : 'draft');
                DB::table('statement_of_accounts')->where('booking_id', $bid)->update([
                    'total_payments' => $paid, 'balance' => $balance, 'status' => $soaStatus,
                ]);
            }

            return ['receipt_number' => $payment->receipt_number];
        });

        // An error array returned from inside the transaction (payment missing/already
        // voided/negative) has a 'type' key; the success path's return doesn't.
        if (isset($result['type'])) {
            return $result;
        }

        ActivityLog::record($uid, 'void', 'billing', 'Payment ' . ($result['receipt_number'] ?? "#$pid") . " voided: $reason", $pid);

        return ['type' => 'success', 'text' => 'Payment voided and a reversing entry recorded.'];
    }

    // Propose happens on the Bookings page (and, for clients, their booking page); this is
    // where accounting/admin staff — and, from the booking detail page, Traffic — sign off.
    // Logic lives in BookingCosting so every entry point shares one implementation.
    private function approveDiscount(Request $request, int $uid): array
    {
        $bid = (int) $request->input('booking_id');
        $discId = (int) $request->input('discount_id');
        $notes = trim((string) $request->input('review_notes', ''));

        return \App\Support\BookingCosting::approveDiscount($bid, $discId, $uid, $notes ?: null);
    }

    private function rejectDiscount(Request $request, int $uid): array
    {
        $bid = (int) $request->input('booking_id');
        $discId = (int) $request->input('discount_id');
        $notes = trim((string) $request->input('review_notes', ''));

        return \App\Support\BookingCosting::rejectDiscount($bid, $discId, $uid, $notes ?: null);
    }
}
