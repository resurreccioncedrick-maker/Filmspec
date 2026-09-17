<?php

namespace App\Http\Controllers;

use App\Support\ChatAttachmentUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClientBookingDetailController extends Controller
{
    public function show(Request $request, int $id)
    {
        $user = Auth::user();
        $role = $user->role->role_name ?? null;

        if (! $user || $role !== 'client') {
            return redirect()->route('login');
        }

        $uid = $user->user_id;

        $booking = DB::table('bookings as b')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('b.booking_id', $id)
            ->where('c.user_id', $uid)
            ->select('b.*', 'c.company_name', 'c.contact_person', 'c.email as client_email', 'c.phone as client_phone', 'c.client_type')
            ->first();

        if (! $booking) {
            return redirect()->route('home');
        }

        $feedbackMsg = null;
        $cancelMsg = null;
        $costApprovalMsg = null;
        $requestMsg = null;
        $chatMsg = null;
        $discountMsg = null;

        $pendingCancel = DB::table('booking_cancellations')
            ->where('booking_id', $id)->where('status', 'pending')
            ->orderByDesc('created_at')->first();

        // A client can only ask for a discount while the booking is still live and nothing
        // has been paid/refunded/written off yet — matches the whereNotIn('booking_status',
        // ['cancelled', 'completed']) idiom already used elsewhere in this controller, plus
        // the payment side so a request can't land after money has already moved.
        $canRequestDiscount = ! in_array($booking->booking_status, ['cancelled', 'completed'], true)
            && ! in_array($booking->payment_status, ['paid', 'refunded', 'cancelled'], true)
            && ! $booking->is_archived;

        if ($request->isMethod('post')) {
            $act = $request->input('action', '');

            if ($act === 'client_approve_cost' && ($booking->cost_approval_status ?? null) === 'pending_client') {
                DB::table('bookings')->where('booking_id', $id)->update(['cost_approval_status' => 'client_approved', 'updated_at' => now()]);
                $booking->cost_approval_status = 'client_approved';
                $costApprovalMsg = ['type' => 'success', 'text' => 'You have approved the cost estimate. The FilmSpec team will proceed with equipment release.'];
            }

            if ($act === 'client_reject_cost' && ($booking->cost_approval_status ?? null) === 'pending_client') {
                DB::table('bookings')->where('booking_id', $id)->update(['cost_approval_status' => 'client_rejected', 'updated_at' => now()]);
                $booking->cost_approval_status = 'client_rejected';
                $costApprovalMsg = ['type' => 'warning', 'text' => 'Cost estimate rejected. The team will adjust and resend. Feel free to reach out to discuss.'];
            }

            if ($act === 'client_cancel_request') {
                $canCancel = in_array($booking->booking_status, ['pending', 'confirmed'], true) && ! $booking->is_archived;
                if ($canCancel && ! $pendingCancel) {
                    $reason = trim($request->input('reason', ''));
                    DB::table('booking_cancellations')->insert([
                        'booking_id' => $id, 'requested_by' => $uid, 'request_type' => 'client_request', 'reason' => $reason,
                    ]);
                    $pendingCancel = DB::table('booking_cancellations')
                        ->where('booking_id', $id)->where('status', 'pending')
                        ->orderByDesc('created_at')->first();
                    $cancelMsg = ['type' => 'success', 'text' => 'Your cancellation request has been submitted. Our team will review it shortly.'];
                }
            }

            if ($act === 'submit_feedback') {
                if ($booking->booking_status === 'completed') {
                    $completedAt = $booking->updated_at;
                    $withinWindow = $completedAt && (time() - strtotime($completedAt)) <= 30 * 86400;
                    if ($withinWindow || ! $completedAt) {
                        $rating = min(5, max(1, (int) $request->input('rating', 5)));
                        $comment = trim($request->input('comment', ''));
                        DB::table('booking_feedback')->updateOrInsert(
                            ['booking_id' => $id],
                            ['submitted_by' => $uid, 'rating' => $rating, 'comment' => $comment, 'submitted_at' => now()]
                        );
                        $feedbackMsg = ['type' => 'success', 'text' => 'Thank you for your feedback!'];
                    } else {
                        $feedbackMsg = ['type' => 'error', 'text' => 'The feedback window (30 days after completion) has closed.'];
                    }
                }
            }

            if ($act === 'request_extension') {
                if (in_array($booking->booking_status, ['confirmed', 'ongoing'], true)) {
                    $hasPendingExt = DB::table('booking_extension_requests')->where('booking_id', $id)->where('status', 'pending')->exists();
                    if ($hasPendingExt) {
                        $requestMsg = ['type' => 'error', 'text' => 'You already have a pending extension request.'];
                    } else {
                        $newEnd = trim($request->input('new_end_date', ''));
                        $reason = trim($request->input('reason', ''));
                        $curEnd = $booking->shoot_date_end;
                        if ($newEnd && $newEnd > $curEnd) {
                            DB::table('booking_extension_requests')->insert([
                                'booking_id' => $id, 'requested_by' => $uid,
                                'current_end_date' => $curEnd, 'requested_end_date' => $newEnd, 'reason' => $reason,
                            ]);
                            $requestMsg = ['type' => 'success', 'text' => 'Extension request submitted. Our team will review it shortly.'];
                        } else {
                            $requestMsg = ['type' => 'error', 'text' => 'New end date must be after the current shoot end date.'];
                        }
                    }
                }
            }

            if ($act === 'post_comment') {
                $body = trim($request->input('body', ''));
                $attach = ChatAttachmentUpload::handle($request->file('attachment'));
                if ($attach['error']) {
                    $chatMsg = ['type' => 'error', 'text' => $attach['error']];
                } elseif ($body !== '' || $attach['success']) {
                    DB::table('booking_comments')->insert([
                        'booking_id' => $id, 'user_id' => $uid, 'body' => $body,
                        'attachment_path' => $attach['success'] ? $attach['path'] : null,
                        'attachment_name' => $attach['success'] ? $attach['name'] : null,
                        'attachment_mime' => $attach['success'] ? $attach['mime'] : null,
                        'attachment_size' => $attach['success'] ? $attach['size'] : null,
                        'is_internal' => false, 'created_at' => now(),
                    ]);
                    $chatMsg = ['type' => 'success', 'text' => 'Your message has been sent.'];
                } else {
                    $chatMsg = ['type' => 'error', 'text' => 'Message cannot be empty.'];
                }
            }

            if ($act === 'request_discount') {
                if (! $canRequestDiscount) {
                    $discountMsg = ['type' => 'error', 'text' => 'Discount requests are not available for this booking right now.'];
                } else {
                    $type = (string) $request->input('discount_type', 'flat');
                    $value = (float) $request->input('discount_value', 0);
                    $reason = trim((string) $request->input('reason', ''));
                    $discountMsg = \App\Support\BookingCosting::proposeDiscount($id, $uid, $type, $value, $reason);
                }
            }

            if ($act === 'request_field_item') {
                if (in_array($booking->booking_status, ['confirmed', 'ongoing'], true)) {
                    $itemType = in_array($request->input('item_type'), ['equipment', 'accessory', 'crew'], true)
                        ? $request->input('item_type') : 'equipment';
                    $qty = max(1, (int) $request->input('quantity', 1));
                    $reason = trim($request->input('reason', ''));

                    if ($itemType === 'equipment') {
                        $eid = (int) $request->input('equipment_id', 0);
                        if ($eid) {
                            $eqRate = (float) DB::table('equipment')->where('equipment_id', $eid)->value('daily_rate');
                            DB::table('booking_equipment_requests')->insert([
                                'booking_id' => $id, 'requested_by' => $uid, 'item_type' => 'equipment',
                                'equipment_id' => $eid, 'quantity' => $qty, 'reason' => $reason, 'daily_rate' => $eqRate,
                            ]);
                            $requestMsg = ['type' => 'success', 'text' => 'Equipment request submitted. The admin will review it shortly.'];
                        } else {
                            $requestMsg = ['type' => 'error', 'text' => 'Please select equipment.'];
                        }
                    } elseif ($itemType === 'accessory') {
                        $aid = (int) $request->input('accessory_id', 0);
                        if ($aid) {
                            $accRate = (float) DB::table('accessories')->where('accessory_id', $aid)->value('daily_rate');
                            DB::table('booking_equipment_requests')->insert([
                                'booking_id' => $id, 'requested_by' => $uid, 'item_type' => 'accessory',
                                'accessory_id' => $aid, 'quantity' => $qty, 'reason' => $reason, 'daily_rate' => $accRate,
                            ]);
                            $requestMsg = ['type' => 'success', 'text' => 'Accessory request submitted. The admin will review it shortly.'];
                        } else {
                            $requestMsg = ['type' => 'error', 'text' => 'Please select an accessory.'];
                        }
                    } else { // crew — client picks a role/position, not a specific person
                        $pid = (int) $request->input('position_id', 0);
                        if ($pid) {
                            DB::table('booking_equipment_requests')->insert([
                                'booking_id' => $id, 'requested_by' => $uid, 'item_type' => 'crew',
                                'position_id' => $pid, 'quantity' => 1, 'reason' => $reason, 'daily_rate' => 0,
                            ]);
                            $requestMsg = ['type' => 'success', 'text' => 'Crew request submitted. The admin will review it shortly.'];
                        } else {
                            $requestMsg = ['type' => 'error', 'text' => 'Please select a role.'];
                        }
                    }
                }
            }
        }

        $feedback = DB::table('booking_feedback')->where('booking_id', $id)->first();
        $feedbackDeadline = null;
        if ($booking->booking_status === 'completed' && $booking->updated_at) {
            $feedbackDeadline = strtotime($booking->updated_at) + 30 * 86400;
        }

        $extensionRequests = DB::table('booking_extension_requests')->where('booking_id', $id)->orderByDesc('created_at')->get();
        $pendingExtension = $extensionRequests->firstWhere('status', 'pending');

        $equipRequests = DB::table('booking_equipment_requests as eqr')
            ->leftJoin('equipment as e', 'eqr.equipment_id', '=', 'e.equipment_id')
            ->leftJoin('accessories as acc', 'eqr.accessory_id', '=', 'acc.accessory_id')
            ->leftJoin('crew_positions as pos', 'eqr.position_id', '=', 'pos.position_id')
            ->leftJoin('crew_members as cm', 'eqr.crew_id', '=', 'cm.crew_id')
            ->leftJoin('vehicle_rates as vr', 'eqr.vehicle_rate_id', '=', 'vr.vehicle_id')
            ->leftJoin('crew_members as drv', 'eqr.driver_crew_id', '=', 'drv.crew_id')
            ->where('eqr.booking_id', $id)->orderByDesc('eqr.created_at')
            ->select(
                'eqr.*', 'e.equipment_name', 'e.brand', 'e.model',
                'acc.accessory_name', 'pos.position_name',
                DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"),
                'vr.label as vehicle_label',
                DB::raw("CONCAT(drv.first_name,' ',drv.last_name) AS driver_name")
            )
            ->get();

        $existingEqIds = DB::table('booking_equipment')->where('booking_id', $id)->pluck('equipment_id');
        $availEquipForRequest = DB::table('equipment')
            ->where('availability_status', 'available')
            ->whereNotIn('equipment_id', $existingEqIds)
            ->orderBy('equipment_name')
            ->limit(100)
            ->select('equipment_id', 'equipment_name', 'brand', 'model', 'daily_rate')
            ->get();

        $existingAccIds = DB::table('booking_accessories')->where('booking_id', $id)->pluck('accessory_id');
        $availAccessoriesForRequest = DB::table('accessories')
            ->whereNotIn('accessory_id', $existingAccIds)
            ->orderBy('accessory_name')
            ->limit(100)
            ->select('accessory_id', 'accessory_name', 'description', 'daily_rate')
            ->get();

        $crewPositions = DB::table('crew_positions')->orderBy('position_name')->get();

        $contractDocuments = DB::table('documents')
            ->where('booking_id', $id)->where('category', 'contract')
            ->orderByDesc('created_at')
            ->get();

        $equipmentLines = DB::table('booking_equipment as be')
            ->join('equipment as e', 'be.equipment_id', '=', 'e.equipment_id')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->where('be.booking_id', $id)
            ->select('be.*', 'e.equipment_name', 'ec.category_name', 'e.brand', 'e.model', DB::raw('(be.quantity * be.days * be.daily_rate) AS subtotal'))
            ->get();

        $crewLines = DB::table('booking_crew as bc')
            ->join('crew_members as cm', 'bc.crew_id', '=', 'cm.crew_id')
            ->leftJoin('crew_positions as cp', 'bc.position_id', '=', 'cp.position_id')
            ->where('bc.booking_id', $id)
            ->select('bc.*', DB::raw("CONCAT(cm.first_name,' ',cm.last_name) AS crew_name"), 'cp.position_name', DB::raw('(bc.rate_used * IF(bc.is_overtime,1.25,1) * IF(bc.is_double_pay,2,1)) AS subtotal'))
            ->get();

        $payments = DB::table('payments')->where('booking_id', $id)->orderByDesc('payment_date')->get();

        $discounts = DB::table('booking_discounts')->where('booking_id', $id)->orderByDesc('discount_id')->get();

        $comments = DB::table('booking_comments as bc')
            ->join('users as u', 'bc.user_id', '=', 'u.user_id')
            ->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->where('bc.booking_id', $id)->where('bc.is_internal', false)
            ->orderBy('bc.created_at')
            ->select('bc.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS author_name"), 'r.role_name as author_role')
            ->get();

        $statusBadge = ['pending' => 'badge-yellow', 'confirmed' => 'badge-blue', 'ongoing' => 'badge-green', 'completed' => 'badge-gray', 'cancelled' => 'badge-red'];
        $payBadge = ['unpaid' => 'badge-yellow', 'partial' => 'badge-orange', 'paid' => 'badge-green', 'overdue' => 'badge-red', 'refunded' => 'badge-purple', 'cancelled' => 'badge-gray'];
        $payLabel = ['unpaid' => 'Pending', 'partial' => 'Partially Paid', 'paid' => 'Fully Paid', 'overdue' => 'Overdue', 'refunded' => 'Refunded', 'cancelled' => 'Cancelled'];

        return view('client-booking-detail', [
            'booking' => $booking, 'id' => $id,
            'feedbackMsg' => $feedbackMsg, 'cancelMsg' => $cancelMsg, 'costApprovalMsg' => $costApprovalMsg, 'requestMsg' => $requestMsg,
            'chatMsg' => $chatMsg, 'discountMsg' => $discountMsg,
            'pendingCancel' => $pendingCancel, 'feedback' => $feedback, 'feedbackDeadline' => $feedbackDeadline,
            'extensionRequests' => $extensionRequests, 'pendingExtension' => $pendingExtension,
            'equipRequests' => $equipRequests, 'availEquipForRequest' => $availEquipForRequest,
            'availAccessoriesForRequest' => $availAccessoriesForRequest, 'crewPositions' => $crewPositions,
            'contractDocuments' => $contractDocuments,
            'equipmentLines' => $equipmentLines, 'crewLines' => $crewLines, 'payments' => $payments, 'comments' => $comments,
            'discounts' => $discounts, 'canRequestDiscount' => $canRequestDiscount,
            'statusBadge' => $statusBadge, 'payBadge' => $payBadge, 'payLabel' => $payLabel,
        ]);
    }

    /**
     * Streams a chat attachment back — 404 (not 403) if the comment doesn't belong to a
     * booking owned by the logged-in client, same "don't reveal it exists" discipline as
     * DocumentController::download().
     */
    public function attachment(Request $request, int $id, int $commentId)
    {
        $user = Auth::user();
        $role = $user->role->role_name ?? null;
        abort_unless($user && $role === 'client', 404);

        $comment = DB::table('booking_comments as bc')
            ->join('bookings as b', 'bc.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('bc.comment_id', $commentId)->where('bc.booking_id', $id)->where('c.user_id', $user->user_id)
            ->select('bc.attachment_path', 'bc.attachment_name')
            ->first();

        abort_unless($comment && $comment->attachment_path, 404);
        abort_unless(Storage::disk('local')->exists($comment->attachment_path), 404);

        return Storage::disk('local')->response($comment->attachment_path, $comment->attachment_name);
    }
}
