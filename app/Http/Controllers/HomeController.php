<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
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

        if ($request->has('eq_detail')) {
            $eid = (int) $request->query('eq_detail');
            $accs = DB::table('accessories as a')
                ->join('equipment_accessory_links as eal', 'eal.accessory_id', '=', 'a.accessory_id')
                ->where('eal.equipment_id', $eid)
                ->where('a.is_active', 1)
                ->where(function ($w) {
                    $w->where('a.accessory_type', 'package_inclusion')
                        ->orWhere(function ($w2) {
                            $w2->where('a.accessory_type', 'optional_addon')->where('a.is_public', 1);
                        });
                })
                ->orderByDesc('a.is_included')
                ->orderBy('a.accessory_name')
                ->select('a.accessory_id', 'a.accessory_name', 'a.description', 'a.daily_rate', 'a.is_included', 'a.image_path', 'a.tracking_method', 'a.quantity')
                ->get();

            $availByAcc = \App\Support\AccessoryAvailability::bulkAvailable($accs->pluck('accessory_id')->all());
            foreach ($accs as $acc) {
                $acc->available_units = $availByAcc[$acc->accessory_id] ?? 0;
            }

            return response()->json($accs);
        }

        $isLoggedIn = (bool) ($user && $role === 'client');
        $uid = $isLoggedIn ? $user->user_id : 0;
        $clientId = $isLoggedIn ? (int) (DB::table('clients')->where('user_id', $uid)->value('client_id') ?? 0) : 0;

        $supportMsg = null;
        if ($isLoggedIn && $request->isMethod('post') && $request->input('action') === 'post_support_message') {
            $body = trim($request->input('body', ''));
            $dept = $request->input('department', '');
            $dept = \App\Support\BookingChatDepartment::isValid($dept) ? $dept : 'staff';
            $attach = ChatAttachmentUpload::handle($request->file('attachment'));
            if ($attach['error']) {
                $supportMsg = ['type' => 'error', 'text' => $attach['error']];
            } elseif (($body !== '' || $attach['success']) && $clientId) {
                DB::table('client_support_messages')->insert([
                    'client_id' => $clientId, 'user_id' => $uid, 'department' => $dept, 'body' => $body,
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

        if ($isLoggedIn && $request->isMethod('post') && $request->input('action') === 'request_data_erasure') {
            $alreadyPending = $clientId && DB::table('data_erasure_requests')
                ->where('client_id', $clientId)->where('status', 'pending')->exists();
            if ($alreadyPending) {
                $accountMsg = ['type' => 'error', 'text' => 'You already have a pending data erasure request — our team will process it soon.'];
            } elseif ($clientId) {
                DB::table('data_erasure_requests')->insert([
                    'client_id' => $clientId, 'reason' => trim((string) $request->input('erasure_reason', '')) ?: null,
                    'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
                ]);
                ActivityLog::record($uid, 'create', 'data_retention', 'Client requested account data erasure (Data Privacy Act)', $clientId);
                $accountMsg = ['type' => 'success', 'text' => 'Your data erasure request has been submitted. Our team will review it and process it shortly — this may take a few business days.'];
            }
        }

        $supportMessages = collect();
        $staffLastRead = null;
        if ($isLoggedIn && $clientId) {
            $supportMessages = DB::table('client_support_messages as m')
                ->join('users as u', 'm.user_id', '=', 'u.user_id')
                ->join('roles as r', 'u.role_id', '=', 'r.role_id')
                ->where('m.client_id', $clientId)->where('m.is_internal', false)
                ->orderBy('m.created_at')
                ->select('m.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS author_name"), 'r.role_name as author_role')
                ->get()
                ->each(function ($m) {
                    $m->author_label = $m->author_role === 'client' ? null : \App\Support\StaffTeamLabel::forRole($m->author_role);
                    $m->department = $m->department ?: 'staff';
                });

            $staffLastRead = DB::table('staff_message_reads')
                ->where('thread_type', 'support')->where('thread_id', $clientId)
                ->max('last_read_at');
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
            ->selectRaw("(SELECT COUNT(*) FROM equipment_units eu WHERE eu.equipment_id = e.equipment_id AND eu.status != 'retired') AS unit_count")
            ->selectRaw("(SELECT COUNT(*) FROM equipment_units eu WHERE eu.equipment_id = e.equipment_id AND eu.status IN ('available','allocated')) AS units_available")
            ->get();

        $pageIds = $equipment->pluck('equipment_id');
        $unitStatusBreakdown = DB::table('equipment_units')
            ->whereIn('equipment_id', $pageIds)
            ->where('status', '!=', 'retired')
            ->select('equipment_id', 'status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('equipment_id', 'status')
            ->get()
            ->groupBy('equipment_id');

        foreach ($equipment as $eq) {
            if ($eq->unit_count > 0) {
                $eq->available_units = (int) $eq->units_available;
                $counts = $unitStatusBreakdown->get($eq->equipment_id, collect())->pluck('cnt', 'status');
                $eq->avail_state = match (true) {
                    $eq->available_units > 0 => 'available',
                    ($counts['in_field'] ?? 0) > 0 => 'in_use',
                    ($counts['under_maintenance'] ?? 0) > 0 => 'maintenance',
                    ($counts['allocated'] ?? 0) > 0 => 'booked',
                    default => 'unavailable',
                };
            } else {
                $eq->available_units = $eq->availability_status === 'available' ? max(1, (int) ($eq->stock_quantity ?? 1)) : 0;
                $eq->avail_state = match ($eq->availability_status) {
                    'available' => 'available',
                    'rented' => 'in_use',
                    'booked' => 'booked',
                    'under_repair' => 'maintenance',
                    default => 'unavailable',
                };
            }
        }

        $equipment = $equipment->sortBy(fn ($eq) => $eq->available_units > 0 ? 0 : 1)->values();

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
            'equip' => (int) DB::table('equipment')->where('availability_status', '!=', 'retired')->count(),
            'crew' => (int) DB::table('crew_members')->where('status', 'active')->count(),
            'done' => (int) DB::table('bookings')->where('booking_status', 'completed')->count(),
        ];

        $statusMap = [
            'pending' => ['#d97706', 'Pending Review'], 'confirmed' => ['#0060C7', 'Confirmed'],
            'ongoing' => ['#16a34a', 'Ongoing'], 'completed' => ['#64748b', 'Completed'], 'cancelled' => ['#dc2626', 'Cancelled'],
        ];

        $erasurePending = $clientId && DB::table('data_erasure_requests')
            ->where('client_id', $clientId)->where('status', 'pending')->exists();

        $chatDepartments = \App\Support\BookingChatDepartment::LABELS;

        return view('home', compact('isLoggedIn', 'user', 'categories', 'equipment', 'clientBookings', 'stats', 'statusMap', 'supportMessages', 'supportMsg', 'staffLastRead', 'faqs', 'publicReviews', 'accountMsg', 'erasurePending', 'chatDepartments'));
    }

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
            ->select('m.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS author_name"), 'r.role_name as author_role')
            ->get()
            ->each(function ($m) {
                $m->author_label = $m->author_role === 'client' ? null : \App\Support\StaffTeamLabel::forRole($m->author_role);
                $m->department = $m->department ?: 'staff';
            });

        $staffLastRead = DB::table('staff_message_reads')
            ->where('thread_type', 'support')->where('thread_id', $clientId)
            ->max('last_read_at');

        return response()->json(['messages' => $messages, 'staff_last_read' => $staffLastRead]);
    }

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
