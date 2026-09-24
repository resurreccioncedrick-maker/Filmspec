<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Client Detail — Overview/Bookings/Billing/Documents/Activity tabs. Didn't exist before this
 * pass (clients.blade.php was list-only); built to match the reference mockup and to give
 * Payment Terms/Loyalty Discount a permission-gated home in the Billing tab, separate from the
 * plain Add/Edit Client modal's basic fields.
 */
class ClientDetailController extends Controller
{
    private array $typeBadge = ['regular' => 'badge-green', 'first_time' => 'badge-blue'];

    private array $typeLabel = ['regular' => 'Regular', 'first_time' => 'New Customer'];

    private array $termLabel = [
        '50_downpayment' => '50% Downpayment', '90_days' => '90 Days',
        '6_months' => '6 Months', '2_weeks_crew' => '2 Weeks (Crew)',
    ];

    private array $entityTypeLabel = [
        'individual' => 'Individual', 'student' => 'Student', 'company' => 'Company',
        'ngo' => 'NGO / Non-Profit', 'government' => 'Government',
    ];

    private array $statusBadge = [
        'pending' => 'badge-yellow', 'confirmed' => 'badge-blue', 'ongoing' => 'badge-green',
        'pending_inspection' => 'badge-purple', 'returned' => 'badge-orange', 'completed' => 'badge-gray',
        'cancelled' => 'badge-red',
    ];

    public function show(Request $request, int $id): View|RedirectResponse
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';

        if (! in_array($role, ['admin', 'super_admin', 'operations_manager', 'traffic'], true)) {
            return redirect()->route('dashboard');
        }

        $client = DB::table('clients')->where('client_id', $id)->first();
        if (! $client) {
            return redirect()->route('clients');
        }

        $canManage = in_array($role, config('filmspec.manage_roles'), true);
        $canSeeBilling = in_array('billing', config("filmspec.role_permissions.$role", []), true);

        $portalAccount = $client->user_id
            ? DB::table('users')->where('user_id', $client->user_id)->select('is_active')->first()
            : null;

        $bookingStats = DB::table('bookings')->where('client_id', $id)
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN booking_status='completed' THEN 1 ELSE 0 END) AS completed")
            ->selectRaw("SUM(CASE WHEN booking_status IN ('confirmed','ongoing') THEN 1 ELSE 0 END) AS active")
            ->selectRaw('MAX(shoot_date_start) AS last_booking_date')
            ->first();

        $bookings = DB::table('bookings')
            ->where('client_id', $id)
            ->orderByDesc('created_at')
            ->limit(25)
            ->select('booking_id', 'booking_reference', 'project_title', 'shoot_date_start', 'shoot_date_end',
                'booking_status', 'payment_status', 'final_amount')
            ->get();

        $billing = null;
        if ($canSeeBilling) {
            $billing = [
                'total_paid' => (float) DB::table('payments as p')
                    ->join('bookings as b', 'p.booking_id', '=', 'b.booking_id')
                    ->where('b.client_id', $id)->sum('p.amount'),
                'outstanding' => (float) DB::table('statement_of_accounts as soa')
                    ->join('bookings as b', 'soa.booking_id', '=', 'b.booking_id')
                    ->where('b.client_id', $id)->where('soa.status', '!=', 'paid')->sum('soa.balance'),
            ];
        }

        // Same query as ClientDocumentsController::show() — the Documents tab now includes the
        // shared partial directly (like Booking Detail already does) instead of iframing that
        // whole separate full-layout page inside this one.
        $documents = DB::table('documents as d')
            ->leftJoin('users as u', 'd.uploaded_by', '=', 'u.user_id')
            ->where('d.client_id', $id)
            ->orderByDesc('d.created_at')
            ->select('d.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS uploaded_by_name"))
            ->get();

        $activity = DB::table('activity_logs as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.user_id')
            ->where('al.module', 'clients')->where('al.record_id', $id)
            ->orderByDesc('al.created_at')
            ->limit(20)
            ->select('al.action', 'al.description', 'al.created_at', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS user_name"))
            ->get();

        return view('client-detail', [
            'client' => $client, 'id' => $id, 'canManage' => $canManage, 'canSeeBilling' => $canSeeBilling,
            'portalAccount' => $portalAccount, 'bookingStats' => $bookingStats, 'bookings' => $bookings,
            'billing' => $billing, 'activity' => $activity, 'documents' => $documents,
            'typeBadge' => $this->typeBadge, 'typeLabel' => $this->typeLabel, 'termLabel' => $this->termLabel,
            'entityTypeLabel' => $this->entityTypeLabel, 'statusBadge' => $this->statusBadge,
        ]);
    }

    /** Mark as Regular / Deactivate / Reactivate — controlled actions triggered from this page's
     *  "More" menu, redirecting back here (unlike ClientsController::handleAction(), which
     *  renders the list page and would otherwise bounce the user away from the detail view). */
    public function action(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        if (! in_array($role, config('filmspec.manage_roles'), true)) {
            abort(403);
        }

        $uid = $user->user_id;
        $action = $request->input('action', '');
        $client = DB::table('clients')->where('client_id', $id)->first();
        if (! $client) {
            return redirect()->route('clients');
        }
        $name = $client->contact_person ?: $client->company_name;

        if ($action === 'mark_regular_client') {
            if ($client->client_type === 'regular') {
                return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'danger', 'text' => 'This client is already a Regular Client.']);
            }
            // Same threshold clients.blade.php uses to flag eligibility — re-checked here
            // server-side so this can't be triggered on an ineligible client.
            $completed = DB::table('bookings')->where('client_id', $id)->where('booking_status', 'completed')->count();
            if ($completed < 4) {
                return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'danger', 'text' => "This client has only $completed completed booking(s) — Regular Client status requires at least 4."]);
            }

            DB::table('clients')->where('client_id', $id)->update(['client_type' => 'regular']);
            ActivityLog::record($uid, 'update', 'clients', "Marked client as Regular Client: $name", $id);

            return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'success', 'text' => "Client <strong>" . e($name) . "</strong> is now a Regular Client."]);
        }

        if ($action === 'deactivate_client') {
            $reason = trim($request->input('reason', ''));
            if ($reason === '') {
                return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'danger', 'text' => 'A reason is required to deactivate a client.']);
            }
            if (! $client->is_active) {
                return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'danger', 'text' => 'This client is already deactivated.']);
            }

            DB::table('clients')->where('client_id', $id)->update([
                'is_active' => false, 'deactivation_reason' => $reason, 'deactivated_at' => now(), 'deactivated_by' => $uid,
            ]);
            ActivityLog::record($uid, 'update', 'clients', "Deactivated client: $name — $reason", $id);

            return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'success', 'text' => "Client <strong>" . e($name) . "</strong> deactivated. Historical bookings, invoices, and payments are preserved — this can be reversed at any time."]);
        }

        if ($action === 'reactivate_client') {
            if ($client->is_active) {
                return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'danger', 'text' => 'This client is already active.']);
            }

            DB::table('clients')->where('client_id', $id)->update([
                'is_active' => true, 'deactivation_reason' => null, 'deactivated_at' => null, 'deactivated_by' => null,
            ]);
            ActivityLog::record($uid, 'update', 'clients', "Reactivated client: $name", $id);

            return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'success', 'text' => "Client <strong>" . e($name) . "</strong> reactivated."]);
        }

        return redirect()->route('client-detail', $id);
    }

    /** Basic-fields save (name/contact/entity/notes) — triggered from this page's Edit Profile
     *  action. Mirrors ClientsController::handleAction()'s edit_client validation, but redirects
     *  back here instead of to the list page. */
    public function updateProfile(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        if (! in_array($role, config('filmspec.manage_roles'), true)) {
            abort(403);
        }

        $phone = trim($request->input('phone', ''));
        if ($phone !== '' && ! preg_match('/^09\d{9}$/', $phone)) {
            return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'danger', 'text' => 'Phone must be 11 digits starting with 09 (e.g. 09171234567).']);
        }

        $email = trim($request->input('email', ''));
        if ($email && DB::table('clients')->where('email', $email)->where('client_id', '!=', $id)->exists()) {
            return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'danger', 'text' => 'Another client already uses this email.']);
        }

        $company = strtoupper(trim($request->input('company_name', '')));
        $contact = strtoupper(trim($request->input('contact_person', '')));
        $entityType = in_array($request->input('entity_type'), array_keys($this->entityTypeLabel), true) ? $request->input('entity_type') : 'individual';

        DB::table('clients')->where('client_id', $id)->update([
            'company_name' => $company ?: null, 'contact_person' => $contact, 'email' => $email, 'phone' => $phone,
            'address' => trim($request->input('address', '')),
            'is_vat_registered' => $entityType === 'company' ? 1 : 0, 'entity_type' => $entityType,
            'notes' => trim($request->input('notes', '')),
        ]);
        ActivityLog::record($user->user_id, 'update', 'clients', "Updated client ID: $id", $id);

        return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'success', 'text' => 'Client profile updated.']);
    }

    /** Billing tab's own save — separate from ClientsController::handleAction()'s edit_client,
     *  since this is deliberately behind the stricter billing-permission gate, not just canManage. */
    public function updateBilling(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        if (! in_array('billing', config("filmspec.role_permissions.$role", []), true)) {
            abort(403);
        }

        $validTerms = array_keys($this->termLabel);
        $terms = in_array($request->input('payment_terms'), $validTerms, true) ? $request->input('payment_terms') : null;
        if (! $terms) {
            return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'danger', 'text' => 'Invalid payment terms.']);
        }
        $discount = min(100, max(0, (float) $request->input('discount_pct', 0)));

        DB::table('clients')->where('client_id', $id)->update([
            'payment_terms' => $terms, 'discount_pct' => $discount,
        ]);
        ActivityLog::record($user->user_id, 'update', 'clients', "Updated billing profile (terms/discount) for client ID: $id", $id);

        return redirect()->route('client-detail', $id)->with('cd_flash', ['type' => 'success', 'text' => 'Billing profile updated.']);
    }
}
