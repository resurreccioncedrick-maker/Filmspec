<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Client;
use App\Models\VehicleRate;
use App\Support\BookingCosting;
use App\Support\DataExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingsController extends Controller
{
    private array $statusBadge = [
        'pending' => 'badge-yellow', 'confirmed' => 'badge-blue', 'ongoing' => 'badge-green',
        'pending_inspection' => 'badge-purple', 'returned' => 'badge-orange', 'completed' => 'badge-gray',
        'cancelled' => 'badge-red',
    ];

    private array $payBadge = [
        'unpaid' => 'badge-yellow', 'partial' => 'badge-orange', 'paid' => 'badge-green',
        'overdue' => 'badge-red', 'refunded' => 'badge-purple', 'cancelled' => 'badge-gray',
    ];

    private array $payLabel = [
        'unpaid' => 'Unpaid', 'partial' => 'Partially Paid', 'paid' => 'Fully Paid',
        'overdue' => 'Overdue', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled',
    ];

    public function index(Request $request): View|StreamedResponse|Response
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $msg = null;

        if ($request->isMethod('post')) {
            $msg = $this->handleAction($request, $user, $role);
        }

        if ($request->filled('export')) {
            return $this->export($request, $user, $role);
        }

        $statusFilter = $request->query('status', '');
        $payFilter = $request->query('pay', '');
        $clientFilter = (int) $request->query('client', 0);
        $clientFilterName = '';
        $search = $request->query('q', '');
        $page = max(1, (int) $request->query('p', 1));
        $perPage = 15;

        $query = $this->filteredQuery($request, $user, $role);

        if ($clientFilter) {
            $fc = DB::table('clients')->where('client_id', $clientFilter)->first();
            $clientFilterName = $fc ? ($fc->company_name ?: $fc->contact_person) : "Client #$clientFilter";
        }

        $total = (clone $query)->count('b.booking_id');
        $pages = max(1, (int) ceil($total / $perPage));

        $bookings = (clone $query)
            ->select('b.*', 'c.company_name', 'c.contact_person', 'c.client_type')
            ->selectRaw('(SELECT COUNT(*) FROM booking_equipment be WHERE be.booking_id = b.booking_id) AS equip_count')
            ->selectRaw('(SELECT COUNT(*) FROM booking_crew bc WHERE bc.booking_id = b.booking_id) AS crew_count')
            ->selectRaw("(SELECT CONCAT(first_name,' ',last_name) FROM users WHERE user_id = b.created_by) AS created_by_name")
            ->selectRaw('(SELECT ce_id FROM cost_estimates ce WHERE ce.booking_id = b.booking_id ORDER BY ce_id DESC LIMIT 1) AS latest_ce_id')
            ->selectRaw("(SELECT CONCAT(discount_type,':',discount_value) FROM booking_discounts bd WHERE bd.booking_id = b.booking_id AND bd.status = 'pending' LIMIT 1) AS pending_discount")
            ->selectRaw("(SELECT discount FROM cost_estimates ce WHERE ce.booking_id = b.booking_id ORDER BY ce_id DESC LIMIT 1) AS active_discount")
            ->orderByDesc('b.created_at')
            ->forPage($page, $perPage)
            ->get();

        $clients = Client::orderBy('contact_person')->get();
        $vehicleRates = VehicleRate::where('is_active', 1)->orderBy('base_rate')->get();
        $vehicleRatesJson = $vehicleRates->keyBy('vehicle_id')->toJson();

        $baseCountQuery = fn () => Booking::query()->when($role === 'client', function ($q) use ($user) {
            $q->whereIn('client_id', function ($sub) use ($user) {
                $sub->select('client_id')->from('clients')->where('user_id', $user->user_id);
            });
        });
        $counts = [];
        foreach (['pending', 'confirmed', 'ongoing', 'pending_inspection', 'returned', 'completed', 'cancelled'] as $s) {
            $counts[$s] = (int) $baseCountQuery()->where('is_archived', false)->where('booking_status', $s)->count();
        }
        $archivedCount = (int) $baseCountQuery()->where('is_archived', true)->count();

        $pendingApprovalCount = in_array($role, ['admin', 'super_admin', 'operations_manager'], true)
            ? (int) Booking::where('approval_status', 'pending_approval')->where('is_archived', false)->count()
            : 0;

        // Same derivation as Dashboard's KPI strip: overdue-for-return is a booking still
        // 'ongoing' past its own shoot end date, since there's no dedicated status for it.
        $overdueReturnsCount = (int) $baseCountQuery()
            ->where('is_archived', false)->where('booking_status', 'ongoing')
            ->where('shoot_date_end', '<', now()->toDateString())->count();
        $openIncidentsCount = (int) DB::table('incident_reports as ir')
            ->join('bookings as b', 'ir.booking_id', '=', 'b.booking_id')
            ->where('ir.status', 'open')
            ->when($role === 'client', fn ($q) => $q->whereIn('b.client_id', function ($sub) use ($user) {
                $sub->select('client_id')->from('clients')->where('user_id', $user->user_id);
            }))
            ->count();
        $upcomingBookingsCount = (int) $baseCountQuery()
            ->where('is_archived', false)->whereIn('booking_status', ['confirmed', 'ongoing'])
            ->whereBetween('shoot_date_start', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->count();

        $baseTransportRate = (float) (DB::table('system_settings')->where('setting_key', 'base_transportation_rate')->value('setting_value') ?: 0);

        return view('bookings', [
            'msg' => $msg,
            'bookings' => $bookings,
            'clients' => $clients,
            'vehicleRates' => $vehicleRates,
            'vehicleRatesJson' => $vehicleRatesJson,
            'baseTransportRate' => $baseTransportRate,
            'statusFilter' => $statusFilter,
            'payFilter' => $payFilter,
            'clientFilter' => $clientFilter,
            'clientFilterName' => $clientFilterName,
            'search' => $search,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
            'counts' => $counts,
            'archivedCount' => $archivedCount,
            'pendingApprovalCount' => $pendingApprovalCount,
            'overdueReturnsCount' => $overdueReturnsCount,
            'openIncidentsCount' => $openIncidentsCount,
            'upcomingBookingsCount' => $upcomingBookingsCount,
            'statusBadge' => $this->statusBadge,
            'payBadge' => $this->payBadge,
            'payLabel' => $this->payLabel,
        ]);
    }

    /** Same filters index() applies, shared with export() so the two can never drift apart. */
    private function filteredQuery(Request $request, $user, string $role)
    {
        $statusFilter = $request->query('status', '');
        $payFilter = $request->query('pay', '');
        $clientFilter = (int) $request->query('client', 0);
        $search = $request->query('q', '');

        $query = Booking::query()->from('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id');

        if ($statusFilter === 'archived') {
            $query->where('b.is_archived', true);
        } else {
            $query->where('b.is_archived', false);
            if ($statusFilter) $query->where('b.booking_status', $statusFilter);
        }
        if ($payFilter) $query->where('b.payment_status', $payFilter);
        if ($clientFilter) {
            $query->where('b.client_id', $clientFilter);
        }
        if ($search) {
            $query->where(function ($w) use ($search) {
                $w->where('b.booking_reference', 'like', "%$search%")
                    ->orWhere('b.project_title', 'like', "%$search%")
                    ->orWhere('c.contact_person', 'like', "%$search%")
                    ->orWhere('c.company_name', 'like', "%$search%");
            });
        }
        if ($role === 'client') {
            $query->whereIn('b.client_id', function ($sub) use ($user) {
                $sub->select('client_id')->from('clients')->where('user_id', $user->user_id);
            });
        }

        return $query;
    }

    /** Export ▾ — reuses filteredQuery() unbounded (no page/perPage) so it always matches what's on screen. */
    private function export(Request $request, $user, string $role): StreamedResponse|Response
    {
        $headers = ['Reference', 'Client', 'Project', 'Shoot Start', 'Shoot End', 'Status', 'Payment', 'Amount'];

        $rows = $this->filteredQuery($request, $user, $role)
            ->select('b.booking_reference', 'b.project_title', 'b.shoot_date_start', 'b.shoot_date_end',
                'b.booking_status', 'b.payment_status', 'b.final_amount', 'c.company_name', 'c.contact_person')
            ->orderByDesc('b.created_at')
            ->get()
            ->map(fn ($b) => [
                $b->booking_reference,
                $b->company_name ?: $b->contact_person,
                $b->project_title,
                Carbon::parse($b->shoot_date_start)->format('M j, Y'),
                Carbon::parse($b->shoot_date_end)->format('M j, Y'),
                ucfirst(str_replace('_', ' ', $b->booking_status)),
                $this->payLabel[$b->payment_status] ?? ucfirst($b->payment_status),
                '₱' . number_format((float) $b->final_amount, 2),
            ])
            ->all();

        return DataExporter::respond($request->query('export'), 'Bookings', $headers, $rows, 'bookings-export');
    }

    private function handleAction(Request $request, $user, string $role): ?array
    {
        $action = $request->input('action', '');
        $uid = $user->user_id;

        // 'accounting' deliberately excluded: this force-sets payment_status directly with no
        // payments-table row, receipt, or SOA sync — accounting's real path is record_payment
        // (BillingController / BookingDetailController), which keeps all of that in sync.
        if ($action === 'update_payment_status' && in_array($role, ['admin', 'super_admin', 'operations_manager'], true)) {
            $bid = (int) $request->input('booking_id');
            $pStatus = $request->input('payment_status');
            $allowed = ['unpaid', 'partial', 'paid', 'overdue', 'refunded', 'cancelled'];
            if ($bid && in_array($pStatus, $allowed, true)) {
                Booking::where('booking_id', $bid)->update(['payment_status' => $pStatus, 'updated_at' => now()]);
                \App\Models\ActivityLog::record($uid, 'update', 'booking', "Payment status → $pStatus for booking ID $bid", $bid);

                return ['type' => 'success', 'text' => 'Payment status updated.'];
            }

            return null;
        }

        if (! in_array($role, ['admin', 'super_admin', 'operations_manager', 'traffic'], true)) {
            return null;
        }

        if ($action === 'update_status') {
            $id = (int) $request->input('booking_id');
            $newStatus = $request->input('booking_status');
            $allowed = ['pending', 'confirmed', 'ongoing', 'completed', 'cancelled'];
            if ($role === 'traffic' && $newStatus === 'confirmed') {
                return ['type' => 'danger', 'text' => 'Bookings must be approved by the Operations Manager before they can be confirmed.'];
            }
            if (in_array($newStatus, $allowed, true)) {
                $old = Booking::where('booking_id', $id)->value('booking_status');
                Booking::where('booking_id', $id)->update(['booking_status' => $newStatus, 'updated_at' => now()]);
                DB::table('quotation_log')->insert([
                    'booking_id' => $id, 'log_type' => $newStatus === 'cancelled' ? 'cancelled' : 'confirmed',
                    'logged_by' => $uid, 'previous_status' => $old, 'new_status' => $newStatus, 'log_date' => now(),
                ]);

                return ['type' => 'success', 'text' => 'Booking status updated.'];
            }

            return null;
        }

        if ($action === 'edit_booking') {
            return $this->editBooking($request);
        }

        if ($action === 'propose_discount' && in_array($role, ['super_admin', 'admin', 'operations_manager', 'traffic'], true)) {
            return $this->proposeDiscount($request, $uid);
        }

        if ($action === 'archive_booking' && in_array($role, ['admin', 'super_admin', 'operations_manager'], true)) {
            $bid = (int) $request->input('booking_id');
            $status = Booking::where('booking_id', $bid)->value('booking_status');
            if (in_array($status, ['pending', 'cancelled'], true)) {
                // A cancelled booking can still have real recorded payments (cancellation only
                // updates payment_status, it never touches the payments rows themselves). That's
                // fine here — archiving doesn't destroy anything, so it's just surfaced as a note
                // rather than blocked like the old hard-delete had to.
                $hasPayments = DB::table('payments')->where('booking_id', $bid)->exists();
                $ref = Booking::where('booking_id', $bid)->value('booking_reference');
                Booking::where('booking_id', $bid)->update([
                    'is_archived' => true, 'archived_at' => now(), 'archived_by' => $uid, 'updated_at' => now(),
                ]);
                \App\Models\ActivityLog::record($uid, 'archive', 'booking', "Archived booking $ref", $bid);
                $note = $hasPayments ? ' It has recorded payments — reconcile via Billing if needed.' : '';

                return ['type' => 'success', 'text' => "Booking <strong>" . e($ref) . "</strong> archived." . $note];
            }

            return ['type' => 'danger', 'text' => 'Only pending or cancelled bookings can be archived.'];
        }

        if ($action === 'unarchive_booking' && in_array($role, ['admin', 'super_admin', 'operations_manager'], true)) {
            $bid = (int) $request->input('booking_id');
            $ref = Booking::where('booking_id', $bid)->value('booking_reference');
            Booking::where('booking_id', $bid)->where('is_archived', true)->update([
                'is_archived' => false, 'archived_at' => null, 'archived_by' => null, 'updated_at' => now(),
            ]);
            \App\Models\ActivityLog::record($uid, 'restore', 'booking', "Restored booking $ref from archive", $bid);

            return ['type' => 'success', 'text' => "Booking <strong>" . e($ref) . "</strong> restored."];
        }

        if ($action === 'approve_booking' && in_array($role, ['admin', 'super_admin', 'operations_manager'], true)) {
            $bid = (int) $request->input('booking_id', 0);
            Booking::where('booking_id', $bid)->where('approval_status', 'pending_approval')->update([
                'approval_status' => 'approved', 'approved_by' => $uid, 'approved_at' => now(),
                'booking_status' => 'confirmed', 'updated_at' => now(),
            ]);
            DB::table('quotation_log')->insert([
                'booking_id' => $bid, 'log_type' => 'confirmed', 'logged_by' => $uid,
                'previous_status' => 'pending', 'new_status' => 'confirmed', 'log_date' => now(),
            ]);
            \App\Models\ActivityLog::record($uid, 'approve', 'booking', "Approved booking ID: $bid", $bid);

            return ['type' => 'success', 'text' => 'Booking approved and confirmed.'];
        }

        if ($action === 'reject_booking' && in_array($role, ['admin', 'super_admin', 'operations_manager'], true)) {
            $bid = (int) $request->input('booking_id', 0);
            $notes = trim($request->input('approval_notes', ''));
            Booking::where('booking_id', $bid)->update(['approval_status' => 'rejected', 'approval_notes' => $notes, 'updated_at' => now()]);
            \App\Models\ActivityLog::record($uid, 'reject', 'booking', "Rejected booking ID: $bid", $bid);

            return ['type' => 'danger', 'text' => 'Booking has been rejected.'];
        }

        if ($action === 'resubmit_booking' && in_array($role, ['traffic', 'admin', 'operations_manager'], true)) {
            $bid = (int) $request->input('booking_id', 0);
            Booking::where('booking_id', $bid)->where('approval_status', 'rejected')->update([
                'approval_status' => 'pending_approval', 'approval_notes' => null, 'updated_at' => now(),
            ]);
            \App\Models\ActivityLog::record($uid, 'resubmit', 'booking', "Resubmitted booking ID: $bid for approval", $bid);

            return ['type' => 'success', 'text' => 'Booking resubmitted for Operations Manager approval.'];
        }

        if ($action === 'add_booking') {
            return $this->addBooking($request, $user, $role);
        }

        return null;
    }

    private function editBooking(Request $request): ?array
    {
        $bid = (int) $request->input('booking_id');
        $ds = $request->input('shoot_date_start', '');
        $de = $request->input('shoot_date_end', '');

        if ($ds && $de && (strtotime($de) - strtotime($ds)) / 86400 > 90) {
            return ['type' => 'error', 'text' => 'Rental period cannot exceed 3 months (90 days).'];
        }

        $zone = $request->input('location_zone', '');
        $zoneMultMap = ['manila' => 1.0, 'luzon' => 1.5, 'luzon_far' => 2.0];
        $multiplier = $zoneMultMap[$zone] ?? 1.0;
        $lat = (float) $request->input('location_lat', 0);
        $lng = (float) $request->input('location_lng', 0);
        $vid = (int) $request->input('vehicle_rate_id', 0);

        if ($bid && $ds && $de) {
            Booking::where('booking_id', $bid)->update([
                'booking_type' => $request->input('booking_type', ''),
                'project_title' => $request->input('project_title', ''),
                'project_type' => $request->input('project_type', ''),
                'shoot_date_start' => $ds,
                'shoot_date_end' => $de,
                'shoot_location' => $request->input('shoot_location', ''),
                'notes' => $request->input('notes', ''),
                // transportation_cost is deliberately not touched here — it's set only via the
                // Assign Transport action on the booking detail page, where it's a manual entry
                // (see BookingDetailController::assignTransport()), not derived from zone/vehicle.
                'delivery_address' => $request->input('delivery_address', ''),
                'location_zone' => $zone ?: null,
                'transport_multiplier' => $multiplier,
                'location_lat' => ($lat != 0 || $lng != 0) ? $lat : null,
                'location_lng' => ($lat != 0 || $lng != 0) ? $lng : null,
                'vehicle_rate_id' => $vid ?: null,
                'updated_at' => now(),
            ]);
            \App\Models\ActivityLog::record(Auth::id(), 'update', 'booking', "Edited booking ID: $bid", $bid);

            return ['type' => 'success', 'text' => 'Booking updated.'];
        }

        return null;
    }

    // Relocated from the Cost Estimate editor (booking detail) — this is now where staff
    // propose a discount; approval happens on the Billing page instead of here, matching how
    // the reference process actually handles discounts (booking, then accounting sign-off).
    private function proposeDiscount(Request $request, int $uid): array
    {
        $bid = (int) $request->input('booking_id');
        $type = (string) $request->input('discount_type', 'flat');
        $value = (float) $request->input('discount_value', 0);
        $reason = trim((string) $request->input('reason', ''));

        return BookingCosting::proposeDiscount($bid, $uid, $type, $value, $reason);
    }

    private function addBooking(Request $request, $user, string $role): ?array
    {
        $clientId = (int) $request->input('client_id', 0);

        if (! $clientId && $request->filled('new_client_name')) {
            $email = $request->input('new_client_email', '');
            if ($email && Client::where('email', $email)->exists()) {
                return ['type' => 'error', 'text' => 'A client with this email already exists. Please select the existing client.'];
            }
            $cpRaw = trim($request->input('new_client_phone', ''));
            if ($cpRaw !== '' && ! preg_match('/^09\d{9}$/', $cpRaw)) {
                return ['type' => 'error', 'text' => 'Phone must be 11 digits starting with 09 (e.g. 09171234567).'];
            }
            try {
                $client = Client::create([
                    'contact_person' => strtoupper(trim($request->input('new_client_name'))),
                    'email' => $email,
                    'phone' => $cpRaw,
                    'client_type' => $request->input('new_client_type', 'first_time'),
                ]);
                $clientId = $client->client_id;
            } catch (\Illuminate\Database\QueryException $e) {
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    return ['type' => 'error', 'text' => 'Client already exists. Please select the existing client.'];
                }
                throw $e;
            }
        }

        if (! $clientId) {
            return null;
        }

        $ds = $request->input('shoot_date_start', '');
        $de = $request->input('shoot_date_end', '');

        if ($ds && ! in_array($role, ['super_admin', 'admin'], true) && strtotime($ds) < strtotime('+48 hours')) {
            return ['type' => 'error', 'text' => 'Bookings must be made at least <strong>48 hours in advance</strong>. Please select a shoot date at least 2 days from today.'];
        }
        if ($ds && $de && (strtotime($de) - strtotime($ds)) / 86400 > 90) {
            return ['type' => 'error', 'text' => 'Rental period cannot exceed 3 months (90 days).'];
        }

        $overlap = Booking::where('client_id', $clientId)
            ->whereNotIn('booking_status', ['completed', 'cancelled'])
            ->where(function ($w) use ($ds, $de) {
                $w->whereBetween('shoot_date_start', [$ds, $de])
                    ->orWhereBetween('shoot_date_end', [$ds, $de])
                    ->orWhereRaw('? BETWEEN shoot_date_start AND shoot_date_end', [$ds]);
            })
            ->exists();
        if ($overlap) {
            return ['type' => 'error', 'text' => 'This client already has a booking with overlapping dates.'];
        }

        $ref = $this->generateBookingRef();
        if (Booking::where('booking_reference', $ref)->exists()) {
            $ref = 'FS-' . date('Y') . '-' . str_pad(time() % 10000, 4, '0', STR_PAD_LEFT);
        }

        $zone = $request->input('location_zone', '');
        $zoneMultMap = ['manila' => 1.0, 'luzon' => 1.5, 'luzon_far' => 2.0];
        $multiplier = $zoneMultMap[$zone] ?? 1.0;
        $lat = (float) $request->input('location_lat', 0);
        $lng = (float) $request->input('location_lng', 0);
        $vid = (int) $request->input('vehicle_rate_id', 0);

        // transportation_cost starts at 0 — it's set later via the Assign Transport action on
        // the booking detail page, where traffic/admin manually enters the actual price.
        $transCost = 0.0;

        $approvalSt = $role === 'traffic' ? 'pending_approval' : 'approved';

        try {
            $booking = Booking::create([
                'booking_reference' => $ref,
                'client_id' => $clientId,
                'booking_type' => $request->input('booking_type'),
                'project_title' => $request->input('project_title', ''),
                'project_type' => $request->input('project_type'),
                'shoot_date_start' => $ds,
                'shoot_date_end' => $de,
                'shoot_location' => $request->input('shoot_location', ''),
                'transportation_cost' => $transCost,
                'delivery_address' => $request->input('delivery_address', ''),
                'notes' => $request->input('notes', ''),
                'created_by' => $user->user_id,
                'location_zone' => $zone ?: null,
                'transport_multiplier' => $multiplier,
                'location_lat' => ($lat != 0 || $lng != 0) ? $lat : null,
                'location_lng' => ($lat != 0 || $lng != 0) ? $lng : null,
                'vehicle_rate_id' => $vid ?: null,
                'approval_status' => $approvalSt,
            ]);
            DB::table('quotation_log')->insert([
                'booking_id' => $booking->booking_id, 'log_type' => 'created', 'logged_by' => $user->user_id,
                'new_status' => 'pending', 'log_date' => now(),
            ]);
            \App\Models\ActivityLog::record($user->user_id, 'create', 'booking', "New booking $ref created", $booking->booking_id);

            return ['type' => 'success', 'text' => "Booking <strong>$ref</strong> created."];
        } catch (\Illuminate\Database\QueryException $e) {
            return ['type' => 'error', 'text' => str_contains($e->getMessage(), 'Duplicate')
                ? 'Duplicate booking detected. Please refresh and try again.'
                : 'Error creating booking. Please try again.'];
        }
    }

    private function generateBookingRef(): string
    {
        $year = date('Y');
        $count = (int) Booking::whereYear('created_at', $year)->count();

        return 'FS-' . $year . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
    }
}
