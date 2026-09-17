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

        $todos = DB::table('reminders')
            ->where('type', 'todo')
            ->where('is_done', false)
            ->orderBy('reminder_date')
            ->get();

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
            'pageActivity' => PageActivity::forModule('reminders'),
            'activityModule' => 'reminders',
            'accessLog' => PageActivity::recentAccess(),
        ]);
    }

    private function handleAction(Request $request, int $uid): ?array
    {
        $action = $request->input('action', '');

        if ($action === 'add_reminder') {
            $type = $request->input('type') === 'partners_meeting' ? 'partners_meeting' : 'todo';
            $title = trim((string) $request->input('title', ''));
            $date = $request->input('reminder_date', '');
            if ($title === '' || $date === '') {
                return ['type' => 'danger', 'text' => 'Title and date are required.'];
            }

            $id = DB::table('reminders')->insertGetId([
                'type' => $type,
                'title' => $title,
                'reminder_date' => $date,
                'meeting_time' => $type === 'partners_meeting' ? ($request->input('meeting_time') ?: null) : null,
                'target_date' => $request->input('target_date') ?: null,
                'location' => $type === 'partners_meeting' ? ($request->input('location') ?: null) : null,
                'person_in_charge' => $request->input('person_in_charge') ?: null,
                'note' => $request->input('note') ?: null,
                'is_done' => false,
                'done_at' => null,
                'visible_to_crew' => $request->boolean('visible_to_crew'),
                'created_by' => $uid,
                'created_at' => now(),
                'updated_at' => now(),
            ], 'reminder_id');

            ActivityLog::record($uid, 'create', 'reminders', "Added " . ($type === 'partners_meeting' ? 'partners meeting' : 'to-do') . " \"$title\".", $id);

            return ['type' => 'success', 'text' => 'Reminder added.'];
        }

        if ($action === 'update_reminder') {
            $id = (int) $request->input('reminder_id');
            $reminder = DB::table('reminders')->where('reminder_id', $id)->first();
            if (! $reminder) {
                return ['type' => 'danger', 'text' => 'Reminder not found.'];
            }

            $type = $request->input('type') === 'partners_meeting' ? 'partners_meeting' : 'todo';
            $title = trim((string) $request->input('title', ''));
            $date = $request->input('reminder_date', '');
            if ($title === '' || $date === '') {
                return ['type' => 'danger', 'text' => 'Title and date are required.'];
            }

            DB::table('reminders')->where('reminder_id', $id)->update([
                'type' => $type,
                'title' => $title,
                'reminder_date' => $date,
                'meeting_time' => $type === 'partners_meeting' ? ($request->input('meeting_time') ?: null) : null,
                'target_date' => $request->input('target_date') ?: null,
                'location' => $type === 'partners_meeting' ? ($request->input('location') ?: null) : null,
                'person_in_charge' => $request->input('person_in_charge') ?: null,
                'note' => $request->input('note') ?: null,
                'visible_to_crew' => $request->boolean('visible_to_crew'),
                'updated_at' => now(),
            ]);

            ActivityLog::record($uid, 'update', 'reminders', "Edited \"$title\".", $id);

            return ['type' => 'success', 'text' => 'Reminder updated.'];
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
