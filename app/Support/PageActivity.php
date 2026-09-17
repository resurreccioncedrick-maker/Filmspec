<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The small contextual feeds the team pages carry (Part 16): "Activity — recent changes" and
 * "Access log — last 7 days".
 *
 * Both read `activity_logs`, which already records logins and logouts under module `auth`
 * complete with IP address — no new instrumentation was needed. Full history lives on the
 * existing Activity page; these are just the recent-events views shown in context.
 */
class PageActivity
{
    /** Recent changes for one module, e.g. 'reminders' or 'repair_purchase'. */
    public static function forModule(string $module, int $limit = 10): Collection
    {
        return DB::table('activity_logs as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.user_id')
            ->where('al.module', $module)
            ->orderByDesc('al.created_at')->orderByDesc('al.log_id')
            ->limit($limit)
            ->select('al.action', 'al.description', 'al.created_at', 'al.ip_address',
                DB::raw("CONCAT(u.first_name,' ',u.last_name) AS user_name"))
            ->get();
    }

    /**
     * Sign-ins and sign-outs over the last N days. Filtered to those two actions specifically
     * — module 'auth' also carries account-lifecycle events (e.g. registration), which the
     * access-log display isn't built to label and would otherwise render as a sign-out.
     */
    public static function recentAccess(int $days = 7, int $limit = 25): Collection
    {
        return DB::table('activity_logs as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.user_id')
            ->leftJoin('roles as r', 'u.role_id', '=', 'r.role_id')
            ->where('al.module', 'auth')
            ->whereIn('al.action', ['login', 'logout'])
            ->where('al.created_at', '>=', now()->subDays($days))
            ->orderByDesc('al.created_at')->orderByDesc('al.log_id')
            ->limit($limit)
            ->select('al.action', 'al.created_at', 'al.ip_address', 'r.role_name',
                DB::raw("CONCAT(u.first_name,' ',u.last_name) AS user_name"))
            ->get();
    }
}
