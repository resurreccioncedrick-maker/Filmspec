<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PageActivity
{
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
