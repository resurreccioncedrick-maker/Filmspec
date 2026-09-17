<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ActivityController extends Controller
{
    private array $actionBadge = [
        'login' => 'badge-green', 'logout' => 'badge-gray', 'create' => 'badge-blue',
        'update' => 'badge-yellow', 'delete' => 'badge-red', 'payment' => 'badge-purple',
    ];

    public function index(Request $request): View
    {
        $search = $request->query('q', '');
        $module = $request->query('module', '');
        $page = max(1, (int) $request->query('p', 1));
        $perPage = 25;

        $query = DB::table('activity_logs as al')->leftJoin('users as u', 'al.user_id', '=', 'u.user_id');
        if ($search) {
            $query->where(function ($w) use ($search) {
                $w->where('al.action', 'like', "%$search%")
                    ->orWhere('al.description', 'like', "%$search%")
                    ->orWhereRaw("CONCAT(u.first_name,' ',u.last_name) LIKE ?", ["%$search%"]);
            });
        }
        if ($module) $query->where('al.module', $module);

        $total = (clone $query)->count();
        $pages = max(1, (int) ceil($total / $perPage));

        $logs = (clone $query)
            ->leftJoin('roles as r', 'u.role_id', '=', 'r.role_id')
            ->orderByDesc('al.created_at')
            ->select('al.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS user_name"), 'u.email as user_email', 'r.role_name')
            ->forPage($page, $perPage)
            ->get();

        $modules = DB::table('activity_logs')->whereNotNull('module')->distinct()->orderBy('module')->pluck('module');

        return view('activity', [
            'logs' => $logs, 'total' => $total, 'pages' => $pages, 'page' => $page,
            'search' => $search, 'module' => $module, 'modules' => $modules,
            'actionBadge' => $this->actionBadge,
        ]);
    }
}
