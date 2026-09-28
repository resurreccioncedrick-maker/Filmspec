<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\PageActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReminderController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $role = $user->role->role_name ?? '';
        $canPost = in_array($role, config('filmspec.all_staff'), true);

        DB::table('reminders')
            ->where('is_done', true)
            ->where('done_at', '<', now()->subDays(45))
            ->delete();

        $msg = null;
        if ($request->isMethod('post') && $canPost) {
            $msg = $this->handleAction($request, $user->user_id);
        }

        $meeting = DB::table('reminders')
            ->where('type', 'partners_meeting')
            ->where('is_done', false)
            ->orderBy('reminder_date')
            ->first();
        if ($meeting) {
            $meeting->attendees = DB::table('reminder_attendees')->where('reminder_id', $meeting->reminder_id)->orderBy('attendee_id')->pluck('name');
        }

        $todos = DB::table('reminders')
            ->where('type', 'todo')
            ->where('is_done', false)
            ->orderBy('reminder_date')
            ->get();

        $assignedNames = DB::table('users')->whereIn('user_id', $todos->pluck('assigned_to')->filter()->push($meeting->assigned_to ?? 0))
            ->pluck(DB::raw("CONCAT(first_name,' ',last_name)"), 'user_id');
        $todos->each(fn ($t) => $t->assigned_name = $assignedNames[$t->assigned_to] ?? null);
        if ($meeting) {
            $meeting->assigned_name = $assignedNames[$meeting->assigned_to] ?? null;
        }

        $relatedLabel = function ($type, $rid) {
            if (! $type || ! $rid) {
                return null;
            }
            return match ($type) {
                'booking' => DB::table('bookings')->where('booking_id', $rid)->value('booking_reference'),
                'client' => DB::table('clients')->where('client_id', $rid)->value(DB::raw("COALESCE(company_name, contact_person)")),
                'equipment' => DB::table('equipment')->where('equipment_id', $rid)->value('equipment_name'),
                'crew' => DB::table('crew_members')->where('crew_id', $rid)->value(DB::raw("CONCAT(first_name,' ',last_name)")),
                default => null,
            };
        };
        $todos->each(fn ($t) => $t->related_label = $relatedLabel($t->related_type, $t->related_id));
        if ($meeting) {
            $meeting->related_label = $relatedLabel($meeting->related_type, $meeting->related_id);
        }

        // Compact pickers for the Related-To and Assigned-To selects — kept small (recent/active
        // only) since this is a lightweight combo box, not a searchable autocomplete.
        $pickerBookings = DB::table('bookings')->orderByDesc('booking_id')->limit(100)
            ->select('booking_id', 'booking_reference', 'project_title')->get();
        $pickerClients = DB::table('clients')->orderBy('company_name')
            ->select('client_id', 'company_name', 'contact_person')->get();
        $pickerEquipment = DB::table('equipment')->orderBy('equipment_name')
            ->select('equipment_id', 'equipment_name')->get();
        $pickerCrew = DB::table('crew_members')->where('status', 'active')->orderBy('first_name')
            ->select('crew_id', DB::raw("CONCAT(first_name,' ',last_name) as name"))->get();
        $pickerStaff = DB::table('users as u')->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->where('r.role_name', '!=', 'client')
            ->orderBy('u.first_name')
            ->select('u.user_id', DB::raw("CONCAT(u.first_name,' ',u.last_name) as name"))->get();
        $roleOptions = config('filmspec.all_staff', []);

        $today = now()->toDateString();
        $kpis = [
            'open_tasks' => $todos->count(),
            'overdue' => $todos->filter(fn ($t) => $t->reminder_date < $today)->count(),
            'upcoming_meetings' => $meeting ? 1 : 0,
            'completed_this_month' => (int) DB::table('reminders')
                ->where('is_done', true)
                ->whereYear('done_at', now()->year)->whereMonth('done_at', now()->month)
                ->count(),
        ];

        $done = DB::table('reminders')
            ->where('is_done', true)
            ->orderByDesc('done_at')
            ->get()
            ->map(function ($r) {
                $daysLeft = 45 - now()->diffInDays($r->done_at);
                $r->days_left = max(0, (int) $daysLeft);
                return $r;
            });

        return view('reminders', [
            'msg' => $msg,
            'canPost' => $canPost,
            'meeting' => $meeting,
            'todos' => $todos,
            'done' => $done,
            'kpis' => $kpis,
            'pickerBookings' => $pickerBookings,
            'pickerClients' => $pickerClients,
            'pickerEquipment' => $pickerEquipment,
            'pickerCrew' => $pickerCrew,
            'pickerStaff' => $pickerStaff,
            'roleOptions' => $roleOptions,
            'pageActivity' => PageActivity::forModule('reminders'),
            'activityModule' => 'reminders',
            'accessLog' => PageActivity::recentAccess(),
        ]);
    }

    private function handleAction(Request $request, int $uid): ?array
    {
        $action = $request->input('action', '');

        if ($action === 'add_reminder' || $action === 'update_reminder') {
            $id = (int) $request->input('reminder_id');
            $isUpdate = $action === 'update_reminder';
            if ($isUpdate && ! DB::table('reminders')->where('reminder_id', $id)->exists()) {
                return ['type' => 'danger', 'text' => 'Reminder not found.'];
            }

            $type = $request->input('type') === 'partners_meeting' ? 'partners_meeting' : 'todo';
            $title = trim((string) $request->input('title', ''));
            $date = $request->input('reminder_date', '');
            if ($title === '' || $date === '') {
                return ['type' => 'danger', 'text' => 'Title and date are required.'];
            }

            $relatedType = $request->input('related_type', '');
            $relatedId = in_array($relatedType, ['booking', 'client', 'equipment', 'crew'], true) ? (int) $request->input('related_id', 0) : null;
            $relatedType = $relatedId ? $relatedType : null;

            $visibility = in_array($request->input('visibility'), ['internal', 'roles', 'crew_portal'], true) ? $request->input('visibility') : 'internal';
            $visibleRoles = $visibility === 'roles' ? implode(',', (array) $request->input('visible_roles', [])) : null;
            // visible_to_crew stays the single source of truth the Crew Portal already reads —
            // the new visibility selector just drives it instead of a standalone checkbox now.
            $visibleToCrew = $visibility === 'crew_portal';

            $fields = [
                'type' => $type,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'priority' => in_array($request->input('priority'), ['low', 'high'], true) ? $request->input('priority') : 'normal',
                'title' => $title,
                'reminder_date' => $date,
                'due_time' => $type === 'todo' ? ($request->input('due_time') ?: null) : null,
                'meeting_time' => $type === 'partners_meeting' ? ($request->input('meeting_time') ?: null) : null,
                'target_date' => $request->input('target_date') ?: null,
                'location' => $type === 'partners_meeting' ? ($request->input('location') ?: null) : null,
                'person_in_charge' => $request->input('person_in_charge') ?: null,
                'assigned_to' => (int) $request->input('assigned_to', 0) ?: null,
                'note' => $request->input('note') ?: null,
                'visible_to_crew' => $visibleToCrew,
                'visibility' => $visibility,
                'visible_roles' => $visibleRoles,
                'updated_at' => now(),
            ];

            if ($isUpdate) {
                DB::table('reminders')->where('reminder_id', $id)->update($fields);
                ActivityLog::record($uid, 'update', 'reminders', "Edited \"$title\".", $id);
            } else {
                $fields += ['is_done' => false, 'done_at' => null, 'created_by' => $uid, 'created_at' => now()];
                $id = DB::table('reminders')->insertGetId($fields, 'reminder_id');
                ActivityLog::record($uid, 'create', 'reminders', "Added " . ($type === 'partners_meeting' ? 'partners meeting' : 'to-do') . " \"$title\".", $id);
            }

            if ($type === 'partners_meeting') {
                DB::table('reminder_attendees')->where('reminder_id', $id)->delete();
                $names = array_values(array_filter(array_map('trim', (array) $request->input('attendees', []))));
                if ($names) {
                    DB::table('reminder_attendees')->insert(array_map(
                        fn ($name) => ['reminder_id' => $id, 'name' => $name, 'created_at' => now()], $names
                    ));
                }
            }

            return ['type' => 'success', 'text' => $isUpdate ? 'Reminder updated.' : 'Reminder added.'];
        }

        if ($action === 'toggle_reminder') {
            $id = (int) $request->input('reminder_id');
            $reminder = DB::table('reminders')->where('reminder_id', $id)->first();
            if (! $reminder) {
                return ['type' => 'danger', 'text' => 'Reminder not found.'];
            }

            $nowDone = ! $reminder->is_done;
            DB::table('reminders')->where('reminder_id', $id)->update([
                'is_done' => $nowDone,
                'done_at' => $nowDone ? now() : null,
                'updated_at' => now(),
            ]);

            ActivityLog::record($uid, 'update', 'reminders', ($nowDone ? 'Marked done: ' : 'Reopened: ') . "\"{$reminder->title}\".", $id);

            return ['type' => 'success', 'text' => $nowDone ? 'Marked as done.' : 'Reopened.'];
        }

        if ($action === 'delete_reminder') {
            $id = (int) $request->input('reminder_id');
            $reminder = DB::table('reminders')->where('reminder_id', $id)->first();
            if (! $reminder) {
                return ['type' => 'danger', 'text' => 'Reminder not found.'];
            }

            DB::table('reminders')->where('reminder_id', $id)->delete();
            ActivityLog::record($uid, 'delete', 'reminders', "Deleted \"{$reminder->title}\".", $id);

            return ['type' => 'success', 'text' => 'Reminder deleted.'];
        }

        return null;
    }
}
