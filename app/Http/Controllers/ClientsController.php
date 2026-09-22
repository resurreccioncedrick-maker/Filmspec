<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\DataExporter;
use App\Support\OtpMailTemplates;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClientsController extends Controller
{
    private array $typeBadge = ['regular' => 'badge-green', 'first_time' => 'badge-blue'];

    private array $typeLabel = ['regular' => 'Regular', 'first_time' => 'New Customer'];

    // clients.payment_terms enum: 50_downpayment/90_days/6_months/2_weeks_crew.
    // Legacy clients.php and booking_detail.php both used a different, invalid set of
    // values here (downpayment_50/pay_later/full_upfront) that silently stored as empty
    // string under this DB's non-strict sql_mode — fixed to use the real enum consistently.
    private array $termLabel = [
        '50_downpayment' => '50% Downpayment', '90_days' => '90 Days',
        '6_months' => '6 Months', '2_weeks_crew' => '2 Weeks (Crew)',
    ];

    private array $entityTypeLabel = [
        'individual' => 'Individual', 'student' => 'Student', 'company' => 'Company',
        'ngo' => 'NGO / Non-Profit', 'government' => 'Government',
    ];

    private array $entityTypeBadge = [
        'individual' => 'badge-blue', 'student' => 'badge-purple', 'company' => 'badge-green',
        'ngo' => 'badge-orange', 'government' => 'badge-gray',
    ];

    public function index(Request $request): View|RedirectResponse|StreamedResponse|Response
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';

        if (! in_array($role, ['admin', 'super_admin', 'operations_manager', 'traffic'], true)) {
            return redirect()->route('dashboard');
        }

        $canManage = in_array($role, config('filmspec.manage_roles'), true);

        $msg = null;
        if ($request->isMethod('post') && $canManage) {
            $msg = $this->handleAction($request);
        }

        if ($request->filled('export')) {
            return $this->export($request);
        }

        $typeFilter = $request->query('type', '');
        $statusFilter = $request->query('status', '');
        $search = $request->query('q', '');
        $page = max(1, (int) $request->query('p', 1));
        $perPage = 20;

        $query = $this->filteredQuery($request);

        $total = (clone $query)->count('c.client_id');
        $pages = max(1, (int) ceil($total / $perPage));

        $clients = (clone $query)
            ->orderBy('c.company_name')->orderBy('c.contact_person')
            ->select('c.*')
            ->forPage($page, $perPage)
            ->get();

        foreach ($clients as $c) {
            $bookingStats = DB::table('bookings')
                ->where('client_id', $c->client_id)
                ->selectRaw('COUNT(*) AS total_bookings')
                ->selectRaw("SUM(CASE WHEN booking_status='completed' THEN 1 ELSE 0 END) AS completed_bookings")
                ->selectRaw('MAX(shoot_date_start) AS last_booking_date')
                ->first();
            $c->total_bookings = $bookingStats->total_bookings;
            $c->completed_bookings = $bookingStats->completed_bookings;
            $c->last_booking_date = $bookingStats->last_booking_date;
        }

        $stats = [
            'total' => (int) DB::table('clients')->count(),
            'regular' => (int) DB::table('clients')->where('client_type', 'regular')->count(),
            'first_time' => (int) DB::table('clients')->where('client_type', 'first_time')->count(),
            'pending' => (int) DB::table('clients')->where('status', 'pending')->count(),
            'new_month' => (int) DB::table('clients')
                ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)
                ->count(),
        ];

        return view('clients', [
            'msg' => $msg, 'canManage' => $canManage,
            'clients' => $clients, 'stats' => $stats, 'total' => $total, 'pages' => $pages, 'page' => $page,
            'typeFilter' => $typeFilter, 'statusFilter' => $statusFilter, 'search' => $search,
            'typeBadge' => $this->typeBadge, 'typeLabel' => $this->typeLabel, 'termLabel' => $this->termLabel,
            'entityTypeLabel' => $this->entityTypeLabel, 'entityTypeBadge' => $this->entityTypeBadge,
        ]);
    }

    /** Same filters index() applies, shared with export() so the two can never drift apart. */
    private function filteredQuery(Request $request)
    {
        $typeFilter = $request->query('type', '');
        $statusFilter = $request->query('status', '');
        $search = $request->query('q', '');

        $query = DB::table('clients as c');
        if ($typeFilter) $query->where('c.client_type', $typeFilter);
        if ($statusFilter) $query->where('c.status', $statusFilter);
        if ($search) {
            $query->where(function ($w) use ($search) {
                $w->where('c.contact_person', 'like', "%$search%")
                    ->orWhere('c.company_name', 'like', "%$search%")
                    ->orWhere('c.email', 'like', "%$search%")
                    ->orWhere('c.phone', 'like', "%$search%");
            });
        }

        return $query;
    }

    /** Export ▾ — reuses filteredQuery() unbounded (no page/perPage) so it always matches what's on screen. */
    private function export(Request $request): StreamedResponse|Response
    {
        $headers = ['Client', 'Type', 'Contact', 'Bookings', 'Completed', 'Last Booking', 'Payment Terms'];

        $rows = $this->filteredQuery($request)
            ->orderBy('c.company_name')->orderBy('c.contact_person')
            ->select('c.*')
            ->get()
            ->map(function ($c) {
                $bookingStats = DB::table('bookings')
                    ->where('client_id', $c->client_id)
                    ->selectRaw('COUNT(*) AS total_bookings')
                    ->selectRaw("SUM(CASE WHEN booking_status='completed' THEN 1 ELSE 0 END) AS completed_bookings")
                    ->selectRaw('MAX(shoot_date_start) AS last_booking_date')
                    ->first();

                return [
                    $c->company_name ?: $c->contact_person,
                    $this->typeLabel[$c->client_type] ?? ucfirst($c->client_type),
                    $c->contact_person,
                    (int) $bookingStats->total_bookings,
                    (int) $bookingStats->completed_bookings,
                    $bookingStats->last_booking_date ? \Illuminate\Support\Carbon::parse($bookingStats->last_booking_date)->format('M j, Y') : '—',
                    $this->termLabel[$c->payment_terms] ?? ucfirst(str_replace('_', ' ', (string) $c->payment_terms)),
                ];
            })
            ->all();

        return DataExporter::respond($request->query('export'), 'Clients', $headers, $rows, 'clients-export');
    }

    private function handleAction(Request $request): ?array
    {
        $action = $request->input('action', '');
        $uid = $request->user()->user_id;
        $validTerms = array_keys($this->termLabel);

        if ($action === 'add_client') {
            $phone = trim($request->input('phone', ''));
            if ($phone !== '' && ! preg_match('/^09\d{9}$/', $phone)) {
                return ['type' => 'danger', 'text' => 'Phone must be 11 digits starting with 09 (e.g. 09171234567).'];
            }

            $company = strtoupper(trim($request->input('company_name', '')));
            $contact = strtoupper(trim($request->input('contact_person', '')));
            $email = trim($request->input('email', ''));

            if (! $contact && ! $company) {
                return ['type' => 'danger', 'text' => 'Contact person or company name is required.'];
            }
            if ($email && DB::table('clients')->where('email', $email)->exists()) {
                return ['type' => 'danger', 'text' => 'A client with this email already exists.'];
            }

            $type = in_array($request->input('client_type'), ['regular', 'first_time'], true) ? $request->input('client_type') : 'first_time';
            $terms = in_array($request->input('payment_terms'), $validTerms, true) ? $request->input('payment_terms') : '50_downpayment';
            $entityType = in_array($request->input('entity_type'), array_keys($this->entityTypeLabel), true) ? $request->input('entity_type') : 'individual';

            $newId = DB::table('clients')->insertGetId([
                'company_name' => $company ?: null, 'contact_person' => $contact, 'email' => $email, 'phone' => $phone,
                'address' => trim($request->input('address', '')), 'client_type' => $type, 'payment_terms' => $terms,
                'is_vat_registered' => $entityType === 'company' ? 1 : 0, 'entity_type' => $entityType,
                'notes' => trim($request->input('notes', '')),
                'discount_pct' => min(100, max(0, (float) $request->input('discount_pct', 0))),
            ]);
            ActivityLog::record($uid, 'create', 'clients', 'Added client: ' . ($company ?: $contact), $newId);

            return ['type' => 'success', 'text' => 'Client <strong>' . e($company ?: $contact) . '</strong> added.'];
        }

        if ($action === 'edit_client') {
            $phone = trim($request->input('phone', ''));
            if ($phone !== '' && ! preg_match('/^09\d{9}$/', $phone)) {
                return ['type' => 'danger', 'text' => 'Phone must be 11 digits starting with 09 (e.g. 09171234567).'];
            }

            $cid = (int) $request->input('client_id');
            $email = trim($request->input('email', ''));
            if ($email && DB::table('clients')->where('email', $email)->where('client_id', '!=', $cid)->exists()) {
                return ['type' => 'danger', 'text' => 'Another client already uses this email.'];
            }

            $company = strtoupper(trim($request->input('company_name', '')));
            $contact = strtoupper(trim($request->input('contact_person', '')));
            $type = in_array($request->input('client_type'), ['regular', 'first_time'], true) ? $request->input('client_type') : 'first_time';
            $entityType = in_array($request->input('entity_type'), array_keys($this->entityTypeLabel), true) ? $request->input('entity_type') : 'individual';

            // payment_terms/discount_pct are deliberately NOT touched here — they moved to the
            // Billing tab's own restricted save (ClientDetailController::updateBilling()), so
            // this basic edit form must never overwrite them back to a default.
            DB::table('clients')->where('client_id', $cid)->update([
                'company_name' => $company ?: null, 'contact_person' => $contact, 'email' => $email, 'phone' => $phone,
                'address' => trim($request->input('address', '')), 'client_type' => $type,
                'is_vat_registered' => $entityType === 'company' ? 1 : 0, 'entity_type' => $entityType,
                'notes' => trim($request->input('notes', '')),
            ]);
            ActivityLog::record($uid, 'update', 'clients', "Updated client ID: $cid", $cid);

            return ['type' => 'success', 'text' => 'Client updated.'];
        }

        if ($action === 'approve_client') {
            $cid = (int) $request->input('client_id');
            $client = DB::table('clients')->where('client_id', $cid)->first();
            if (! $client) {
                return ['type' => 'danger', 'text' => 'Client not found.'];
            }

            DB::table('clients')->where('client_id', $cid)->update(['status' => 'approved', 'rejection_reason' => null]);
            $name = $client->contact_person ?: $client->company_name;
            ActivityLog::record($uid, 'update', 'clients', "Approved client: $name", $cid);

            if ($client->email) {
                $this->sendMail($client->email, 'Your FilmSpec Account is Approved', OtpMailTemplates::clientApproved($name));
            }

            return ['type' => 'success', 'text' => 'Client <strong>' . e($name) . '</strong> approved.'];
        }

        if ($action === 'reject_client') {
            $cid = (int) $request->input('client_id');
            $reason = trim($request->input('reason', ''));
            if ($reason === '') {
                return ['type' => 'danger', 'text' => 'A reason is required to reject a client.'];
            }

            $client = DB::table('clients')->where('client_id', $cid)->first();
            if (! $client) {
                return ['type' => 'danger', 'text' => 'Client not found.'];
            }

            DB::table('clients')->where('client_id', $cid)->update(['status' => 'rejected', 'rejection_reason' => $reason]);
            $name = $client->contact_person ?: $client->company_name;
            ActivityLog::record($uid, 'update', 'clients', "Rejected client: $name", $cid);

            if ($client->email) {
                $this->sendMail($client->email, 'FilmSpec Account Application Update', OtpMailTemplates::clientRejected($name, $reason));
            }

            return ['type' => 'success', 'text' => 'Client <strong>' . e($name) . '</strong> rejected.'];
        }

        return null;
    }

    private function sendMail(string $to, string $subject, string $html): void
    {
        try {
            Mail::html($html, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::error('Client approval mail failed: ' . $e->getMessage());
        }
    }
}
