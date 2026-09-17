<?php

namespace App\Http\Controllers;

use App\Support\BookingCosting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CePreviewController extends Controller
{
    public function index(Request $request)
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        if ($request->filled('booking_id')) {
            return $this->bookingMode($request);
        }

        $uid = Auth::id();

        $cartItems = DB::table('booking_cart as c')
            ->leftJoin('equipment as e', 'c.equipment_id', '=', 'e.equipment_id')
            ->leftJoin('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->leftJoin('crew_members as cm', 'c.crew_id', '=', 'cm.crew_id')
            ->leftJoin('crew_positions as cp', 'c.position_id', '=', 'cp.position_id')
            ->where('c.user_id', $uid)
            ->select('c.*', 'e.equipment_name', 'e.brand', 'e.model', 'e.daily_rate', 'e.requires_operator', 'ec.category_name', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"), 'cm.base_rate_12hr', 'cp.position_name')
            ->get();

        if ($cartItems->isEmpty()) {
            return redirect()->route('home');
        }

        $equipLines = $cartItems->where('item_type', 'equipment')->values();
        $equipTotal = (float) $equipLines->sum(fn ($e) => (float) $e->daily_rate * $e->quantity * $e->days);

        $estimatedCrewLines = [];
        foreach ($equipLines as $eq) {
            if ($eq->requires_operator) {
                $ops = DB::table('equipment_operators as eo')
                    ->join('crew_positions as cp', 'eo.position_id', '=', 'cp.position_id')
                    ->leftJoin('crew_members as cm', function ($j) {
                        $j->on('cm.primary_position_id', '=', 'eo.position_id')->where('cm.status', 'active');
                    })
                    ->where('eo.equipment_id', $eq->equipment_id)
                    ->groupBy('eo.position_id', 'cp.position_name')
                    ->select('eo.position_id', 'cp.position_name', DB::raw('COALESCE(AVG(cm.base_rate_12hr),0) AS avg_rate'))
                    ->get();
                foreach ($ops as $op) {
                    $days = (int) $eq->days;
                    $avgRate = (float) $op->avg_rate;
                    $estimatedCrewLines[] = (object) [
                        'equipment_name' => $eq->equipment_name,
                        'position_name' => $op->position_name,
                        'avg_rate' => $avgRate,
                        'days' => $days,
                        'est_total' => $avgRate * $days,
                    ];
                }
            }
        }

        $user = Auth::user();
        $clientName = trim($user->first_name . ' ' . $user->last_name);
        $ceNumber = 'DRAFT-' . date('Ymd');

        $baseTransRate = (float) (DB::table('system_settings')->where('setting_key', 'base_transportation_rate')->value('setting_value') ?: 0);

        // Prices are VAT-inclusive. Extract the VAT component via the configurable rate,
        // matching BookingCosting::extractVat() (not a hardcoded 12/112).
        $vatRate = (float) config('filmspec.vat_rate');
        $grand = max(0, $equipTotal);
        $vat = round($grand * $vatRate / (1 + $vatRate), 2);
        $subtotal = $grand - $vat;

        $infoRows = [
            'PRODUCTION HOUSE' => strtoupper($clientName),
            'PROJECT NAME' => '(TO BE PROVIDED ON BOOKING)',
            'CONTACT PERSON' => strtoupper($clientName),
            'SHOOT DATE(S)' => '(TO BE PROVIDED ON BOOKING)',
            'LOCATION' => '(TO BE PROVIDED ON BOOKING)',
        ];

        $mode = 'cart';
        $crewTotal = 0.0;
        $ceBd = null;
        $ceBdDiscounted = false;
        $cePricingMode = 'no_discount';
        $cePricingInput = null;
        $accLines = [];
        $accTotal = 0.0;
        $transCost = 0.0;
        $transZone = '';
        $transMult = 1.0;
        $zoneLabels = [];

        return view('ce-preview', compact(
            'mode', 'equipLines', 'equipTotal', 'estimatedCrewLines', 'crewTotal', 'clientName',
            'ceNumber', 'baseTransRate', 'grand', 'vat', 'subtotal', 'infoRows', 'ceBd', 'ceBdDiscounted',
            'cePricingMode', 'cePricingInput', 'accLines', 'accTotal', 'transCost', 'transZone', 'transMult', 'zoneLabels'
        ));
    }

    // Booking-mode CE — an existing booking's confirmed/draft cost estimate, viewed by staff
    // (internal or "for client" toggle) or by the client themselves (always forced to the
    // client view). Reuses BookingCosting::breakdown()/lines(), the same helpers the Package &
    // Totals panel and CE export already use, so this document, the panel, and the CSV never
    // drift out of sync with each other.
    private function bookingMode(Request $request)
    {
        $bid = (int) $request->query('booking_id');
        $uid = Auth::id();
        $role = Auth::user()->role->role_name ?? 'client';

        // A client-role viewer is ALWAYS forced to the client view no matter what the URL says —
        // the toggle is a staff convenience, not an access control, and must not be bypassable
        // by hand-editing the query string.
        $isClientView = $role === 'client' || $request->query('view') === 'client';

        $bookingQuery = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $bid);

        // A client can only ever load their own booking's CE — never trust booking_id alone
        // for a client-role viewer, matching the same check ClientBookingDetailController::show()
        // already applies.
        if ($role === 'client') {
            $bookingQuery->where('c.user_id', $uid);
        }

        $booking = $bookingQuery
            ->select('b.*', 'c.company_name', 'c.contact_person', 'c.email as client_email', 'c.phone as client_phone', 'c.entity_type')
            ->first();
        if (! $booking) {
            return $role === 'client' ? redirect()->route('home') : redirect()->route('bookings');
        }

        $ceId = (int) $request->query('ce_id');
        $ce = $ceId
            ? DB::table('cost_estimates')->where('booking_id', $bid)->where('ce_id', $ceId)->first()
            : null;
        if (! $ce) {
            $ce = DB::table('cost_estimates')->where('booking_id', $bid)->orderByDesc('ce_id')->first();
        }

        $lines = BookingCosting::lines($bid);
        $equipGroups = $lines['groups'];
        $crewLines = $lines['crew'];

        $accLines = DB::table('booking_accessories as ba')
            ->join('accessories as a', 'ba.accessory_id', '=', 'a.accessory_id')
            ->where('ba.booking_id', $bid)
            ->orderBy('a.accessory_name')
            ->select('ba.*', 'a.accessory_name')
            ->get();
        $accTotal = (float) $accLines->sum('subtotal');

        $equipBaseTotal = 0.0;
        foreach ($equipGroups as $catLines) {
            foreach ($catLines as $eq) {
                $equipBaseTotal += (float) $eq->quantity * (float) $eq->daily_rate;
            }
        }
        $crewTotal = (float) DB::table('booking_crew')->where('booking_id', $bid)
            ->selectRaw('COALESCE(SUM(rate_used * hours_worked),0) AS t')->value('t');

        $transCost = (float) ($booking->transportation_cost ?? 0);
        $transZone = $booking->location_zone ?? '';
        $transMult = max(1.0, (float) ($booking->transport_multiplier ?? 1.0));
        $equipTotal = $equipBaseTotal * $transMult;

        $zoneLabels = [
            'manila' => 'Metro Manila / NCR (0–60 km)',
            'luzon' => 'Luzon Near (61–150 km)',
            'luzon_far' => 'Luzon Far (151 km+)',
        ];

        $ceDiscount = $ce ? (float) ($ce->discount ?? 0) : 0;
        $ceOtherCharges = $ce ? (float) ($ce->other_charges ?? 0) : 0;
        $cePricingMode = $ce ? ($ce->pricing_mode ?? 'no_discount') : 'no_discount';
        $cePricingInput = $ce && $ce->pricing_input !== null ? (float) $ce->pricing_input : null;
        $ceVatExempt = $ce ? (bool) $ce->vat_exempt : false;

        // Equipment-vs-crew split of the billed total (BookingCosting::breakdown()). Only
        // meaningful once a CE exists — without one the sections fall back to the listed
        // rate-card totals with VAT extracted, same as cart mode.
        $ceBd = $ce ? BookingCosting::breakdown($bid, $ce) : null;
        $ceBdDiscounted = $ceBd && $ceBd['pricing_mode'] !== 'no_discount';

        $itemTotal = $equipTotal + $crewTotal + $accTotal + $transCost;

        if ($cePricingMode !== 'no_discount' && $cePricingInput !== null) {
            // Package pricing: a negotiated all-in price is quoted NET of VAT — opposite
            // convention from rate-card prices — with 12% added on top.
            $netBase = match ($cePricingMode) {
                'package_price' => $cePricingInput,
                'discount_percent' => $itemTotal * (1 - min(100, $cePricingInput) / 100),
                'discount_flat' => $itemTotal - $cePricingInput,
                default => $itemTotal,
            };
            $netBase = max(0, $netBase);
            $vatRate = $ceVatExempt ? 0.0 : (float) config('filmspec.vat_rate');
            $vat = round($netBase * $vatRate, 2);
            $subtotal = $netBase;
            $grand = $netBase + $vat;
        } else {
            // Prices are VAT-inclusive. Extract the VAT component via the configurable rate,
            // matching BookingCosting::extractVat() (not a hardcoded 12/112).
            $vatRate = (float) config('filmspec.vat_rate');
            $grand = max(0, $itemTotal + $ceOtherCharges - $ceDiscount);
            $vat = round($grand * $vatRate / (1 + $vatRate), 2);
            $subtotal = $grand - $vat;
        }

        $projectName = $booking->project_title ?: 'PROJECT';
        $clientName = $booking->company_name ?: $booking->contact_person;
        $shootDate = $booking->shoot_date_start ? date('F j, Y', strtotime($booking->shoot_date_start)) : '—';
        $location = $booking->shoot_location ?? '';
        $ceNumber = $ce ? $ce->ce_reference : ('CE-' . date('Y') . '-' . str_pad((string) $bid, 4, '0', STR_PAD_LEFT));

        $ceTypeLabels = ['fs_front' => 'FS FRONT', 'client_direct' => 'CLIENT DIRECT', 'partner_front' => 'PARTNER FRONT'];
        $infoRows = [
            'PRODUCTION HOUSE' => strtoupper((string) $clientName),
            'PROJECT NAME' => $projectName !== 'PROJECT' ? '"' . strtoupper($projectName) . '"' : '—',
            'CONTACT PERSON' => strtoupper((string) (($booking->ce_contact_person ?? '') ?: ($booking->contact_person ?? ''))),
            'SHOOT DATE(S)' => $shootDate !== '—' ? strtoupper($shootDate) : '—',
            'LOCATION' => $location !== '' ? strtoupper($location) : '—',
        ];
        $ceContactNo = ($booking->ce_contact_number ?? '') ?: ($booking->client_phone ?? '');
        $ceContactEmail = ($booking->ce_contact_email ?? '') ?: ($booking->client_email ?? '');
        if ($ceContactNo) {
            $infoRows['CONTACT NUMBER'] = strtoupper($ceContactNo);
        }
        if ($ceContactEmail) {
            $infoRows['EMAIL'] = $ceContactEmail;
        }
        if (! empty($booking->ce_director_dop)) {
            $infoRows['DIRECTOR / DOP'] = strtoupper($booking->ce_director_dop);
        }
        if (! empty($booking->ce_due_date)) {
            $infoRows['DUE DATE'] = strtoupper(date('F j, Y', strtotime($booking->ce_due_date)));
        }
        $infoRows['TYPE'] = $ceTypeLabels[$booking->ce_type ?? 'fs_front'] ?? 'FS FRONT';

        $mode = 'booking';

        return view('ce-preview', [
            'mode' => $mode, 'bid' => $bid, 'ceId' => $ce->ce_id ?? 0, 'role' => $role, 'isClientView' => $isClientView,
            'equipGroups' => $equipGroups, 'crewLines' => $crewLines, 'accLines' => $accLines, 'accTotal' => $accTotal,
            'equipBaseTotal' => $equipBaseTotal, 'equipTotal' => $equipTotal, 'crewTotal' => $crewTotal,
            'transCost' => $transCost, 'transZone' => $transZone, 'transMult' => $transMult, 'zoneLabels' => $zoneLabels,
            'cePricingMode' => $cePricingMode, 'cePricingInput' => $cePricingInput, 'ceVatExempt' => $ceVatExempt,
            'ceBd' => $ceBd, 'ceBdDiscounted' => $ceBdDiscounted, 'grand' => $grand, 'vat' => $vat, 'subtotal' => $subtotal,
            'clientName' => $clientName, 'ceNumber' => $ceNumber, 'infoRows' => $infoRows,
            // Cart-mode-only values the shared view still references — safe empty defaults.
            'equipLines' => [], 'estimatedCrewLines' => [], 'baseTransRate' => 0,
        ]);
    }
}
