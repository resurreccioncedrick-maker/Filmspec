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
            'billing' => $billing, 'activity' => $activity,
            'typeBadge' => $this->typeBadge, 'typeLabel' => $this->typeLabel, 'termLabel' => $this->termLabel,
            'entityTypeLabel' => $this->entityTypeLabel, 'statusBadge' => $this->statusBadge,
        ]);
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
