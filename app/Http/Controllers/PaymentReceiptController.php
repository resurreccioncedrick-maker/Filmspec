<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentReceiptController extends Controller
{
    public function show(Request $request, int $id): View
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';

        $payment = DB::table('payments as p')
            ->join('bookings as b', 'p.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->leftJoin('users as u', 'p.received_by', '=', 'u.user_id')
            ->where('p.payment_id', $id)
            ->select(
                'p.*', 'b.booking_reference', 'b.project_title', 'b.client_id',
                'c.company_name', 'c.contact_person', 'c.user_id as client_user_id',
                DB::raw("CONCAT(u.first_name,' ',u.last_name) AS received_by_name")
            )
            ->first();

        abort_unless($payment, 404);

        $allowed = $role === 'client'
            ? ((int) $payment->client_user_id === $user->user_id)
            : in_array('billing', config("filmspec.role_permissions.$role", []), true);
        abort_unless($allowed, 404);

        $typeLabel = ['downpayment' => 'Downpayment', 'progress' => 'Progress Payment', 'final' => 'Final Payment'];
        $methodLabel = ['cash' => 'Cash', 'gcash' => 'GCash', 'bank_transfer' => 'Bank Transfer'];
        $receiptTypeLabel = ['official_receipt' => 'Official Receipt', 'acknowledgement_receipt' => 'Acknowledgement Receipt'];

        return view('payment-receipt', [
            'payment' => $payment, 'role' => $role,
            'typeLabel' => $typeLabel, 'methodLabel' => $methodLabel, 'receiptTypeLabel' => $receiptTypeLabel,
        ]);
    }

    public function proof(Request $request, int $id): StreamedResponse
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';

        $payment = DB::table('payments as p')
            ->join('bookings as b', 'p.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('p.payment_id', $id)
            ->select('p.proof_of_payment_path', 'c.user_id as client_user_id')
            ->first();

        abort_unless($payment && $payment->proof_of_payment_path, 404);

        $allowed = $role === 'client'
            ? ((int) $payment->client_user_id === $user->user_id)
            : in_array('billing', config("filmspec.role_permissions.$role", []), true);
        abort_unless($allowed, 404);

        abort_unless(Storage::disk('local')->exists($payment->proof_of_payment_path), 404);

        return Storage::disk('local')->response($payment->proof_of_payment_path);
    }

    public function submissionProof(Request $request, int $id): StreamedResponse
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        abort_unless(in_array('billing', config("filmspec.role_permissions.$role", []), true), 404);

        $path = DB::table('client_payment_submissions')->where('submission_id', $id)->value('proof_of_payment_path');

        abort_unless($path, 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
