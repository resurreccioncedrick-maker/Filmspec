<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingPrintController extends Controller
{
    public function show(Request $request)
    {
        $printReceipt = (int) $request->query('print_receipt', 0);
        $printSoa = (int) $request->query('print_soa', 0);
        $printInvoice = (int) $request->query('print_invoice', 0);

        if ($printInvoice) {
            return $this->invoice($request, $printInvoice);
        }

        $pay = null;
        $type = null;
        $soa = null;
        $payLines = collect();
        $equipLines = collect();
        $crewLines = collect();
        $incidentCharges = collect();

        if ($printReceipt) {
            $pay = DB::table('payments as p')
                ->join('bookings as b', 'p.booking_id', '=', 'b.booking_id')
                ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->where('p.payment_id', $printReceipt)
                ->select('p.*', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start',
                    'c.company_name', 'c.contact_person', 'c.address', 'c.is_vat_registered')
                ->first();
            if (! $pay) {
                return redirect()->route('billing');
            }
            $type = $pay->is_vat ? 'OFFICIAL RECEIPT' : 'ACKNOWLEDGEMENT RECEIPT';
        }

        if ($printSoa) {
            $soa = DB::table('statement_of_accounts as s')
                ->join('bookings as b', 's.booking_id', '=', 'b.booking_id')
                ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->leftJoin('users as u', 's.prepared_by', '=', 'u.user_id')
                ->where('s.soa_id', $printSoa)
                ->select('s.*', 'b.booking_reference', 'b.project_title', 'b.shoot_date_start', 'b.shoot_date_end',
                    'b.booking_type', 'b.project_type', 'b.transportation_cost', 'b.delivery_address',
                    'c.company_name', 'c.contact_person', 'c.address',
                    'c.email as client_email', 'c.phone as client_phone',
                    DB::raw("CONCAT(u.first_name,' ',u.last_name) AS prepared_by_name"))
                ->first();
            if (! $soa) {
                return redirect()->route('billing');
            }

            $bid = (int) $soa->booking_id;

            $payLines = DB::table('payments')->where('booking_id', $bid)->orderBy('payment_date')->get();
            $equipLines = DB::table('booking_equipment as be')
                ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
                ->where('be.booking_id', $bid)
                ->select('be.*', 'e.equipment_name', 'e.brand')
                ->get();
            $crewLines = DB::table('booking_crew as bc')
                ->join('crew_members as cm', 'bc.crew_id', '=', 'cm.crew_id')
                ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
                ->where('bc.booking_id', $bid)
                ->select('bc.*', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"), 'cp.position_name')
                ->get();
            $incidentCharges = DB::table('incident_reports as ir')
                ->join('equipment as e', 'ir.equipment_id', '=', 'e.equipment_id')
                ->where('ir.booking_id', $bid)
                ->where('ir.charge_amount', '>', 0)
                ->where('ir.status', '!=', 'closed')
                ->select('ir.*', 'e.equipment_name')
                ->get();
        }

        // Close-button fallback: window.close() only works when this view was opened via
        // window.open()/target="_blank" — most of the links into this page are plain same-tab
        // <a href>, where close() is silently refused by the browser. Give the view a real
        // booking to go back to instead.
        $fallbackBookingId = ($soa->booking_id ?? null) ?: ($pay->booking_id ?? null);

        return view('billing-print', [
            'printReceipt' => $printReceipt, 'printSoa' => $printSoa,
            'pay' => $pay, 'type' => $type, 'soa' => $soa,
            'payLines' => $payLines, 'equipLines' => $equipLines,
            'crewLines' => $crewLines, 'incidentCharges' => $incidentCharges,
            'fallbackBookingId' => $fallbackBookingId,
        ]);
    }

    /**
     * The company's actual BIR-style VAT invoice, generated from an existing Statement of
     * Account — same underlying charges (equipment/crew/transportation/incidents) as the SOA,
     * just re-presented as a formal invoice: one Description line per nonzero charge category
     * (ex-VAT), a Sub-Total/VAT/Invoice Total/Amount Due block, bank remittance details, and
     * (when crew was billed) an ANNEX page listing crew headcount by position.
     */
    private function invoice(Request $request, int $soaId)
    {
        $soa = DB::table('statement_of_accounts as s')
            ->join('bookings as b', 's.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('s.soa_id', $soaId)
            ->select('s.*', 'b.booking_reference', 'b.project_title', 'b.transportation_cost',
                'c.company_name', 'c.contact_person', 'c.address', 'c.tin')
            ->first();
        if (! $soa) {
            return redirect()->route('billing');
        }

        $bid = (int) $soa->booking_id;

        $equipTotal = (float) DB::table('booking_equipment')->where('booking_id', $bid)->sum('subtotal');
        $crewTotal = (float) DB::table('booking_crew')->where('booking_id', $bid)->sum('subtotal');
        $accTotal = (float) DB::table('booking_accessories')->where('booking_id', $bid)->sum('subtotal');
        $transTotal = (float) ($soa->transportation_cost ?? 0);
        $incidentTotal = (float) DB::table('incident_reports')
            ->where('booking_id', $bid)->where('charge_amount', '>', 0)->where('status', '!=', 'closed')
            ->sum('charge_amount');

        // A VAT-exempt CE (cost_estimates.vat_exempt) prices with no VAT baked in at all — the
        // same flag CePreviewController/BookingCosting honor when computing this booking's
        // grand total in the first place. Extracting VAT at the standard rate regardless would
        // fabricate a VAT line and understate Net Amount on a document that must show neither.
        $isVatExempt = (bool) DB::table('cost_estimates')
            ->where('booking_id', $bid)->where('status', 'confirmed')
            ->orderByDesc('ce_id')->value('vat_exempt');

        $vatRate = $isVatExempt ? 0.0 : (float) config('filmspec.vat_rate');
        $extractNet = fn ($grossAmt) => $vatRate > 0 ? round($grossAmt / (1 + $vatRate), 2) : round($grossAmt, 2);

        $lines = [];
        if ($equipTotal > 0) $lines[] = ['label' => 'Equipment Rental', 'amount' => $extractNet($equipTotal)];
        if ($crewTotal > 0) $lines[] = ['label' => 'Manpower Talent Fee', 'amount' => $extractNet($crewTotal)];
        if ($accTotal > 0) $lines[] = ['label' => 'Accessories', 'amount' => $extractNet($accTotal)];
        if ($transTotal > 0) $lines[] = ['label' => 'Transportation', 'amount' => $extractNet($transTotal)];
        if ($incidentTotal > 0) $lines[] = ['label' => 'Incident Charges', 'amount' => $extractNet($incidentTotal)];

        $grand = (float) $soa->total_charges;
        $net = $extractNet($grand);
        $vat = round($grand - $net, 2);

        // total_charges (bookings.final_amount) can reflect package pricing/discounts that
        // don't map linearly onto the category sums above — reconcile with an explicit line
        // rather than let the printed lines silently not add up to the stated Sub-Total.
        $lineSum = round(array_sum(array_column($lines, 'amount')), 2);
        $gap = round($net - $lineSum, 2);
        if (abs($gap) >= 0.01) {
            $lines[] = ['label' => $gap < 0 ? 'Package Discount' : 'Other Charges', 'amount' => $gap];
        }

        $crewByPosition = collect();
        if ($crewTotal > 0) {
            $crewByPosition = DB::table('booking_crew as bc')
                ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
                ->where('bc.booking_id', $bid)
                ->select('cp.position_name', DB::raw('COUNT(*) as qty'))
                ->groupBy('cp.position_name')
                ->orderByDesc('qty')
                ->get();
        }
        $crewCount = (int) DB::table('booking_crew')->where('booking_id', $bid)->count();

        // The invoice number tracks the SOA it was generated from (same booking, same billing
        // event) rather than a separate sequence — INV in place of FB keeps it recognizably
        // paired with that Statement of Account.
        $invoiceNumber = str_replace('FB-', '', $soa->soa_reference ?: ('SOA-' . $bid));

        $data = [
            'soa' => $soa, 'invoiceNumber' => $invoiceNumber, 'lines' => $lines,
            'net' => $net, 'vat' => $vat, 'grand' => $grand,
            'totalPayments' => (float) $soa->total_payments, 'balance' => (float) $soa->balance,
            'crewByPosition' => $crewByPosition, 'crewCount' => $crewCount,
            'vatRatePct' => round($vatRate * 100),
        ];

        if ($request->query('export') === 'pdf') {
            $html = view('invoice-print', $data)->render();
            $filename = 'INV-' . preg_replace('/[^A-Za-z0-9_-]/', '', $invoiceNumber) . '.pdf';

            return \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setPaper('a4', 'portrait')->download($filename);
        }

        return view('invoice-print', $data);
    }
}
