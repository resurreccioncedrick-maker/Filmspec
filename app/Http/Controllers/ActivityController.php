<?php

namespace App\Http\Controllers;

use App\Support\DataExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityController extends Controller
{
    private array $actionBadge = [
        'login' => 'badge-green', 'logout' => 'badge-gray', 'create' => 'badge-blue',
        'update' => 'badge-yellow', 'delete' => 'badge-red', 'payment' => 'badge-purple',
    ];

    public function index(Request $request): View|StreamedResponse|Response
    {
        if ($request->filled('export')) {
            return $this->export($request);
        }

        $search = $request->query('q', '');
        $module = $request->query('module', '');
        $page = max(1, (int) $request->query('p', 1));
        $perPage = 25;

        $query = $this->filteredQuery($request);

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

    /** Same filters index() applies, shared with export() so the two can never drift apart. */
    private function filteredQuery(Request $request)
    {
        $search = $request->query('q', '');
        $module = $request->query('module', '');

        $query = DB::table('activity_logs as al')->leftJoin('users as u', 'al.user_id', '=', 'u.user_id');
        if ($search) {
            $query->where(function ($w) use ($search) {
                $w->where('al.action', 'like', "%$search%")
                    ->orWhere('al.description', 'like', "%$search%")
                    ->orWhereRaw("CONCAT(u.first_name,' ',u.last_name) LIKE ?", ["%$search%"]);
            });
        }
        if ($module) $query->where('al.module', $module);

        return $query;
    }

    /** Export ▾ — reuses filteredQuery() unbounded (no page/perPage) so it always matches what's on screen. */
    private function export(Request $request): StreamedResponse|Response
    {
        $headers = ['Time', 'User', 'Role', 'Action', 'Module', 'Description', 'IP Address'];

        $rows = $this->filteredQuery($request)
            ->leftJoin('roles as r', 'u.role_id', '=', 'r.role_id')
            ->orderByDesc('al.created_at')
            ->select('al.created_at', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS user_name"),
                'r.role_name', 'al.action', 'al.module', 'al.description', 'al.ip_address')
            ->get()
            ->map(fn ($l) => [
                Carbon::parse($l->created_at)->format('M j, Y g:ia'),
                trim((string) $l->user_name) ?: 'System',
                $l->role_name ? ucfirst(str_replace('_', ' ', $l->role_name)) : '—',
                ucfirst($l->action),
                $l->module ?: '—',
                $l->description,
                $l->ip_address ?: '—',
            ])
            ->all();

        return DataExporter::respond($request->query('export'), 'Activity Log', $headers, $rows, 'activity-export');
    }
}
