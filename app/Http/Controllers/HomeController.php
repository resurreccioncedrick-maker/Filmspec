<?php

namespace App\Http\Controllers;

use App\Support\ChatAttachmentUpload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $role = $user->role->role_name ?? null;

        if ($user && $role !== 'client') {
            return redirect()->route('dashboard');
        }

        // AJAX: equipment detail (description + accessories) — mirrors index.php's ?eq_detail=X branch
        if ($request->has('eq_detail')) {
            $eid = (int) $request->query('eq_detail');
            $accs = DB::table('accessories as a')
                ->join('equipment_accessory_links as eal', 'eal.accessory_id', '=', 'a.accessory_id')
                ->where('eal.equipment_id', $eid)
                ->orderByDesc('a.is_included')
                ->orderBy('a.accessory_name')
                ->select('a.accessory_id', 'a.accessory_name', 'a.description', 'a.daily_rate', 'a.is_included', 'a.image_path')
                ->get();

            return response()->json($accs);
        }

        $isLoggedIn = (bool) ($user && $role === 'client');
        $uid = $isLoggedIn ? $user->user_id : 0;
        $clientId = $isLoggedIn ? (int) (DB::table('clients')->where('user_id', $uid)->value('client_id') ?? 0) : 0;

        $supportMsg = null;
        if ($isLoggedIn && $request->isMethod('post') && $request->input('action') === 'post_support_message') {
            $body = trim($request->input('body', ''));
            $attach = ChatAttachmentUpload::handle($request->file('attachment'));
            if ($attach['error']) {
                $supportMsg = ['type' => 'error', 'text' => $attach['error']];
            } elseif (($body !== '' || $attach['success']) && $clientId) {
                DB::table('client_support_messages')->insert([
                    'client_id' => $clientId, 'user_id' => $uid, 'body' => $body,
                    'attachment_path' => $attach['success'] ? $attach['path'] : null,
                    'attachment_name' => $attach['success'] ? $attach['name'] : null,
                    'attachment_mime' => $attach['success'] ? $attach['mime'] : null,
                    'attachment_size' => $attach['success'] ? $attach['size'] : null,
                    'is_internal' => false, 'created_at' => now(),
                ]);
                $supportMsg = ['type' => 'success', 'text' => 'Your message has been sent.'];
            } else {
                $supportMsg = ['type' => 'error', 'text' => 'Message cannot be empty.'];
            }
        }

        $accountMsg = null;
        if ($isLoggedIn && $request->isMethod('post') && $request->input('action') === 'update_profile') {
            $phone = trim($request->input('phone', ''));
            if ($phone !== '' && ! preg_match('/^09\d{9}$/', $phone)) {
                $accountMsg = ['type' => 'error', 'text' => 'Phone must be 11 digits starting with 09 (e.g. 09171234567).'];
            } else {
                $fn = strtoupper(trim($request->input('first_name', '')));
                $ln = strtoupper(trim($request->input('last_name', '')));
                DB::table('users')->where('user_id', $uid)->update([
                    'first_name' => $fn, 'last_name' => $ln, 'phone' => $phone, 'updated_at' => now(),
                ]);
                $user = $user->fresh();
                $accountMsg = ['type' => 'success', 'text' => 'Profile updated.'];
            }
        }

        if ($isLoggedIn && $request->isMethod('post') && $request->input('action') === 'change_password') {
            $curr = $request->input('current_password', '');
            $new = $request->input('new_password', '');
            $conf = $request->input('confirm_password', '');
            $hash = DB::table('users')->where('user_id', $uid)->value('password_hash');

            if (! Hash::check($curr, $hash)) {
                $accountMsg = ['type' => 'error', 'text' => 'Current password is incorrect.'];
            } elseif (strlen($new) < 8) {
                $accountMsg = ['type' => 'error', 'text' => 'New password must be at least 8 characters.'];
            } elseif ($new !== $conf) {
                $accountMsg = ['type' => 'error', 'text' => 'Passwords do not match.'];
            } else {
                DB::table('users')->where('user_id', $uid)->update([
                    'password_hash' => Hash::make($new), 'updated_at' => now(),
                ]);
                $accountMsg = ['type' => 'success', 'text' => 'Password changed successfully.'];
            }
        }

        $supportMessages = collect();
        if ($isLoggedIn && $clientId) {
            $supportMessages = DB::table('client_support_messages as m')
                ->join('users as u', 'm.user_id', '=', 'u.user_id')
                ->join('roles as r', 'u.role_id', '=', 'r.role_id')
                ->where('m.client_id', $clientId)->where('m.is_internal', false)
                ->orderBy('m.created_at')
                ->select('m.*', 'r.role_name as author_role')
                ->get();
        }

        $publicReviews = DB::table('booking_feedback as bf')
            ->join('users as u', 'bf.submitted_by', '=', 'u.user_id')
            ->where('bf.rating', '>=', 4)
            ->whereNotNull('bf.comment')->where('bf.comment', '!=', '')
            ->orderByDesc('bf.submitted_at')
            ->limit(6)
            ->select('bf.rating', 'bf.comment', 'bf.submitted_at', 'u.first_name', 'u.last_name')
            ->get();

        $faqs = DB::table('faqs')
            ->where('is_active', true)
            ->orderBy('category')->orderBy('sort_order')->orderBy('faq_id')
            ->get()
            ->groupBy('category');

        $categories = DB::table('equipment_categories')->orderBy('category_name')->get();

        $equipment = DB::table('equipment as e')
            ->join('equipment_categories as ec', 'e.category_id', '=', 'ec.category_id')
            ->where('e.availability_status', '!=', 'retired')
            ->orderBy('ec.category_name')->orderBy('e.equipment_name')
            ->limit(80)
            ->select('e.*', 'ec.category_name', 'ec.category_id')
            ->selectRaw("(SELECT GROUP_CONCAT(cp.position_name SEPARATOR ', ')
                          FROM equipment_operators eo
                          JOIN crew_positions cp ON eo.position_id = cp.position_id
                          WHERE eo.equipment_id = e.equipment_id) AS operator_positions")
            ->get();

        $clientBookings = [];
        if ($isLoggedIn) {
            $clientBookings = DB::table('bookings as b')
                ->join('clients as c', 'b.client_id', '=', 'c.client_id')
                ->where('c.user_id', $uid)
                ->orderByDesc('b.created_at')
                ->limit(20)
                ->select('b.booking_id', 'b.booking_reference', 'b.project_title', 'b.booking_type', 'b.booking_status', 'b.shoot_date_start', 'b.shoot_date_end', 'b.final_amount', 'b.cost_approval_status')
                ->selectRaw('(SELECT COUNT(*) FROM booking_equipment be WHERE be.booking_id = b.booking_id) AS equip_count')
                ->selectRaw('(SELECT COUNT(*) FROM booking_crew bc WHERE bc.booking_id = b.booking_id) AS crew_count')
                ->get();
        }

        $stats = [
            'equip' => (int) DB::table('equipment')->where('availability_status', 'available')->count(),
            'crew' => (int) DB::table('crew_members')->where('status', 'active')->count(),
            'done' => (int) DB::table('bookings')->where('booking_status', 'completed')->count(),
        ];

        $statusMap = [
            'pending' => ['#d97706', 'Pending Review'], 'confirmed' => ['#0060C7', 'Confirmed'],
            'ongoing' => ['#16a34a', 'Ongoing'], 'completed' => ['#64748b', 'Completed'], 'cancelled' => ['#dc2626', 'Cancelled'],
        ];

        return view('home', compact('isLoggedIn', 'user', 'categories', 'equipment', 'clientBookings', 'stats', 'statusMap', 'supportMessages', 'supportMsg', 'faqs', 'publicReviews', 'accountMsg'));
    }

    /**
     * Lightweight polling for the homepage support widget — returns messages newer than
     * `after` (a message_id) as JSON so the client-side JS can append them without a full
     * page reload. Same client-scoped, non-internal filter as index()'s $supportMessages.
     */
    public function supportPoll(Request $request)
    {
        $user = Auth::user();
        $role = $user?->role->role_name ?? null;
        abort_unless($user && $role === 'client', 403);

        $clientId = (int) (DB::table('clients')->where('user_id', $user->user_id)->value('client_id') ?? 0);
        $after = (int) $request->query('after', 0);

        $messages = DB::table('client_support_messages as m')
            ->join('users as u', 'm.user_id', '=', 'u.user_id')
            ->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->where('m.client_id', $clientId)->where('m.is_internal', false)
            ->where('m.message_id', '>', $after)
            ->orderBy('m.created_at')
            ->select('m.*', 'r.role_name as author_role')
            ->get();

        return response()->json(['messages' => $messages]);
    }

    /** Streams a general-support-chat attachment — 404 unless it belongs to this client. */
    public function supportAttachment(int $messageId)
    {
        $user = Auth::user();
        $role = $user->role->role_name ?? null;
        abort_unless($user && $role === 'client', 404);

        $clientId = (int) (DB::table('clients')->where('user_id', $user->user_id)->value('client_id') ?? 0);

        $message = DB::table('client_support_messages')
            ->where('message_id', $messageId)->where('client_id', $clientId)
            ->where('is_internal', false)
            ->select('attachment_path', 'attachment_name')
            ->first();

        abort_unless($message && $message->attachment_path, 404);
        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);

        return Storage::disk('local')->response($message->attachment_path, $message->attachment_name);
    }
}
