<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Support\DatabaseBackup;
use App\Support\DataExporter;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SuperAdminController extends Controller
{
    // Mirrors legacy $ROLES_DEF — the 'admin' role exists in the DB (grandfathered accounts)
    // but is intentionally not assignable from this UI, matching legacy exactly.
    private array $rolesDef = [
        'super_admin' => ['label' => 'Super Admin', 'color' => '7c3aed', 'desc' => 'Full access — manage users, roles, all system data'],
        'operations_manager' => ['label' => 'Operations Manager', 'color' => '0891b2', 'desc' => 'Equipment, crew, bookings, billing, reports, user management'],
        'traffic' => ['label' => 'Traffic', 'color' => 'd97706', 'desc' => 'Create and update bookings and crew assignments'],
        'accounting' => ['label' => 'Accounting', 'color' => '16a34a', 'desc' => 'Billing, payments, receipts and reports'],
        'client' => ['label' => 'Client', 'color' => '6b7280', 'desc' => 'Client portal — view own bookings only'],
        'crew' => ['label' => 'Crew Member', 'color' => '0f766e', 'desc' => 'Crew portal — view assignments and file incident reports'],
    ];

    // Every slug that appears anywhere in config('filmspec.role_permissions') — kept complete
    // so the Role Access Matrix never has to fall back to a raw, un-humanized slug.
    private array $moduleLabels = [
        'dashboard' => 'Dashboard', 'equipment' => 'Equipment', 'accessories' => 'Accessories',
        'crew' => 'Crew', 'bookings' => 'Bookings', 'clients' => 'Clients',
        'billing' => 'Billing & POS', 'reports' => 'Reports', 'users' => 'Users',
        'activity' => 'Audit Log', 'profile' => 'Profile', 'superadmin' => 'Super Admin Panel',
        'attendance' => 'Attendance', 'pos' => 'POS', 'incidents' => 'Incident Reports',
        'transport' => 'Transport', 'reminders' => 'Reminders', 'faqs' => 'FAQs',
        'field_requests' => 'Field Requests', 'repair_purchase' => 'Repairs & Purchases',
        'cost_estimates' => 'Cost Estimates', 'crew_data' => 'Crew Data',
        'equipment_data' => 'Equipment Data',
        'calendar_data' => 'Calendar', 'support_chat' => 'Support Chat',
        'data_retention' => 'Data Retention', 'portal' => 'Client Portal',
        'crew_portal' => 'Crew Portal',
    ];

    // Category grouping for the Role Access Matrix checklist — purely a presentation grouping,
    // doesn't affect what config('filmspec.role_permissions') actually grants.
    private array $moduleGroups = [
        'Operations' => ['dashboard', 'bookings', 'clients', 'crew', 'equipment', 'accessories', 'attendance', 'calendar_data'],
        'Finance' => ['billing', 'pos', 'cost_estimates', 'repair_purchase', 'reports'],
        'Field & Incidents' => ['incidents', 'transport', 'field_requests'],
        'Communication' => ['reminders', 'faqs', 'support_chat'],
        'Data & Reporting' => ['crew_data', 'equipment_data'],
        'Administration' => ['users', 'activity', 'superadmin', 'data_retention'],
        'Account' => ['profile'],
    ];

    public function index(Request $request): View|StreamedResponse|Response
    {
        $actorId = $request->user()->user_id;
        $msg = null;

        if ($request->isMethod('post')) {
            $msg = $this->handleAction($request, $actorId);
        }

        if ($request->filled('export')) {
            return $this->export($request);
        }

        // Ensure all defined roles exist (matches legacy's self-healing role seed)
        foreach ($this->rolesDef as $rn => $rd) {
            DB::table('roles')->where('role_name', $rn)->exists()
                ?: DB::table('roles')->insert(['role_name' => $rn, 'description' => $rd['desc']]);
        }

        $users = DB::table('users as u')
            ->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->orderBy('r.role_id')->orderBy('u.last_name')
            ->select('u.*', 'r.role_name')
            ->selectRaw('(SELECT COUNT(*) FROM activity_logs al WHERE al.user_id = u.user_id) AS action_count')
            ->selectRaw("(SELECT MAX(created_at) FROM activity_logs al WHERE al.user_id = u.user_id AND al.action = 'login') AS last_login")
            ->get();

        $stats = [
            'total_users' => $users->count(),
            'active_users' => $users->where('is_active', 1)->count(),
            'super_admins' => $users->where('role_name', 'super_admin')->count(),
            'admins' => $users->whereIn('role_name', ['admin', 'super_admin'])->count(),
        ];

        $recentActivity = DB::table('activity_logs as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.user_id')
            ->leftJoin('roles as r', 'u.role_id', '=', 'r.role_id')
            ->orderByDesc('al.created_at')
            ->select('al.*', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS user_name"), 'r.role_name')
            ->limit(20)
            ->get();

        $activeSessions = DB::table('activity_logs as al')
            ->join('users as u', 'al.user_id', '=', 'u.user_id')
            ->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->where('al.action', 'login')
            ->where('al.created_at', '>=', now()->subHours(2))
            ->groupBy('u.user_id', 'u.first_name', 'u.last_name', 'r.role_name')
            ->select(DB::raw("CONCAT(u.first_name,' ',u.last_name) AS user_name"), 'r.role_name', 'u.user_id')
            ->selectRaw('MAX(al.created_at) AS last_active')
            ->orderByDesc('last_active')
            ->get();

        $unlinkedCrewMembers = DB::table('crew_members as cm')
            ->leftJoin('crew_positions as cp', 'cm.primary_position_id', '=', 'cp.position_id')
            ->where('cm.status', 'active')
            ->where(function ($w) {
                $w->whereNull('cm.user_id')->orWhere('cm.user_id', 0);
            })
            ->orderBy('cm.last_name')->orderBy('cm.first_name')
            ->select('cm.crew_id', 'cm.first_name', 'cm.last_name', 'cp.position_name')
            ->get();

        $permissions = config('filmspec.role_permissions');

        return view('superadmin', [
            'msg' => $msg, 'users' => $users, 'stats' => $stats,
            'recentActivity' => $recentActivity, 'activeSessions' => $activeSessions,
            'unlinkedCrewMembers' => $unlinkedCrewMembers,
            'rolesDef' => $this->rolesDef, 'moduleLabels' => $this->moduleLabels,
            'moduleGroups' => $this->moduleGroups, 'permissions' => $permissions,
        ]);
    }

    // Streams the live database as a downloadable .sql file. Read-only — never mutates
    // anything, so unlike restore() this is safe to run without a confirmation step.
    public function backup(): Response
    {
        $sql = DatabaseBackup::dump();
        $filename = 'filmspec_backup_' . now()->format('Y-m-d_His') . '.sql';

        return response($sql, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length' => (string) strlen($sql),
        ]);
    }

    /** Export ▾ — two independent datasets: the user list, and the full (uncapped) activity log. */
    private function export(Request $request): StreamedResponse|Response
    {
        $type = $request->query('export', 'users');

        if ($type === 'activity') {
            $headers = ['Time', 'User', 'Role', 'Action', 'Module', 'Description'];
            $rows = DB::table('activity_logs as al')
                ->leftJoin('users as u', 'al.user_id', '=', 'u.user_id')
                ->leftJoin('roles as r', 'u.role_id', '=', 'r.role_id')
                ->orderByDesc('al.created_at')
                ->select('al.created_at', DB::raw("CONCAT(u.first_name,' ',u.last_name) AS user_name"), 'r.role_name', 'al.action', 'al.module', 'al.description')
                ->get()
                ->map(fn ($a) => [
                    Carbon::parse($a->created_at)->format('M j, Y g:ia'),
                    trim((string) $a->user_name) ?: 'System',
                    $a->role_name ? ucfirst(str_replace('_', ' ', $a->role_name)) : '—',
                    ucfirst($a->action), $a->module ?: '—', $a->description,
                ])
                ->all();

            return DataExporter::respond($request->query('format', 'csv'), 'Activity Log', $headers, $rows, 'superadmin-activity-export');
        }

        $headers = ['Name', 'Email', 'Role', 'Status', 'Last Login'];
        $rows = DB::table('users as u')
            ->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->orderBy('r.role_id')->orderBy('u.last_name')
            ->select('u.first_name', 'u.last_name', 'u.email', 'r.role_name', 'u.is_active')
            ->selectRaw("(SELECT MAX(created_at) FROM activity_logs al WHERE al.user_id = u.user_id AND al.action = 'login') AS last_login")
            ->get()
            ->map(fn ($u) => [
                trim($u->first_name . ' ' . $u->last_name),
                $u->email,
                $this->rolesDef[$u->role_name]['label'] ?? ucfirst($u->role_name),
                $u->is_active ? 'Active' : 'Deactivated',
                $u->last_login ? Carbon::parse($u->last_login)->format('M j, Y g:ia') : 'Never',
            ])
            ->all();

        return DataExporter::respond($request->query('format', 'csv'), 'Users', $headers, $rows, 'superadmin-users-export');
    }

    private function handleAction(Request $request, int $actorId): ?array
    {
        $action = $request->input('action', '');

        if ($action === 'add_user') {
            $fn = strtoupper(trim($request->input('first_name', '')));
            $ln = strtoupper(trim($request->input('last_name', '')));
            $em = trim($request->input('email', ''));
            $ph = trim($request->input('phone', ''));
            $role = $request->input('role_name', '');
            $pw = $request->input('password', '');

            if (! $fn || ! $ln || ! $em || strlen($pw) < 6) {
                return ['type' => 'danger', 'text' => 'All fields required. Password min 6 characters.'];
            }
            if ($ph !== '' && ! preg_match('/^09\d{9}$/', $ph)) {
                return ['type' => 'danger', 'text' => 'Phone must be 11 digits starting with 09 (e.g. 09171234567).'];
            }
            if (DB::table('users')->where('email', $em)->exists()) {
                return ['type' => 'danger', 'text' => 'Email already registered.'];
            }
            if (! array_key_exists($role, $this->rolesDef)) {
                return ['type' => 'danger', 'text' => 'Invalid role selected.'];
            }

            $roleId = DB::table('roles')->where('role_name', $role)->value('role_id');
            if (! $roleId) {
                $roleId = DB::table('roles')->insertGetId(['role_name' => $role, 'description' => $this->rolesDef[$role]['desc']]);
            }

            $uid = DB::table('users')->insertGetId([
                'role_id' => $roleId, 'first_name' => $fn, 'last_name' => $ln, 'email' => $em,
                'password_hash' => Hash::make($pw), 'phone' => $ph, 'is_active' => 1,
            ]);

            if ($role === 'crew') {
                $crewMemberId = (int) $request->input('crew_member_id', 0);
                if ($crewMemberId) {
                    DB::table('crew_members')->where('crew_id', $crewMemberId)
                        ->where(function ($w) {
                            $w->whereNull('user_id')->orWhere('user_id', 0);
                        })
                        ->update(['user_id' => $uid]);
                }
            }

            ActivityLog::record($actorId, 'create', 'user', "Created user $em ($role)", $uid);

            return ['type' => 'success', 'text' => "User <strong>$fn $ln</strong> ($role) created successfully."];
        }

        if ($action === 'edit_user') {
            $uid = (int) $request->input('user_id');
            $fn = strtoupper(trim($request->input('first_name', '')));
            $ln = strtoupper(trim($request->input('last_name', '')));
            $ph = trim($request->input('phone', ''));
            $role = $request->input('role_name', '');
            $active = (int) $request->input('is_active');

            if ($ph !== '' && ! preg_match('/^09\d{9}$/', $ph)) {
                return ['type' => 'danger', 'text' => 'Phone must be 11 digits starting with 09 (e.g. 09171234567).'];
            }
            if ($uid === $actorId && $role !== 'super_admin') {
                return ['type' => 'danger', 'text' => 'You cannot change your own role.'];
            }
            if (! array_key_exists($role, $this->rolesDef)) {
                return ['type' => 'danger', 'text' => 'Invalid role selected.'];
            }

            $roleId = DB::table('roles')->where('role_name', $role)->value('role_id');
            if (! $roleId) {
                $roleId = DB::table('roles')->insertGetId(['role_name' => $role, 'description' => $this->rolesDef[$role]['desc'] ?? '']);
            }

            $old = DB::table('users as u')->join('roles as r', 'u.role_id', '=', 'r.role_id')
                ->where('u.user_id', $uid)->select('u.first_name', 'u.last_name', 'r.role_name')->first();

            DB::table('users')->where('user_id', $uid)->update([
                'role_id' => $roleId, 'first_name' => $fn, 'last_name' => $ln, 'phone' => $ph, 'is_active' => $active,
            ]);

            ActivityLog::record($actorId, 'update', 'user', "Updated user #$uid — role {$old->role_name} → $role, active=$active", $uid);

            return ['type' => 'success', 'text' => 'User updated successfully.'];
        }

        if ($action === 'reset_pw') {
            $uid = (int) $request->input('user_id');
            $pw = $request->input('new_password', '');
            if (strlen($pw) < 6) {
                return ['type' => 'danger', 'text' => 'Password must be at least 6 characters.'];
            }

            DB::table('users')->where('user_id', $uid)->update(['password_hash' => Hash::make($pw)]);
            ActivityLog::record($actorId, 'update', 'user', "Reset password for user #$uid", $uid);

            return ['type' => 'success', 'text' => 'Password reset successfully.'];
        }

        if ($action === 'toggle_user') {
            $uid = (int) $request->input('user_id');
            $stat = (int) $request->input('is_active');
            if ($uid === $actorId) {
                return ['type' => 'danger', 'text' => 'Cannot deactivate your own account.'];
            }

            DB::table('users')->where('user_id', $uid)->update(['is_active' => $stat]);
            ActivityLog::record($actorId, 'update', 'user', ($stat ? 'Activated' : 'Deactivated') . " user #$uid", $uid);

            return ['type' => 'success', 'text' => 'User status updated.'];
        }

        if ($action === 'delete_user') {
            $uid = (int) $request->input('user_id');
            if ($uid === $actorId) {
                return ['type' => 'danger', 'text' => 'Cannot delete your own account.'];
            }

            $uname = DB::table('users')->where('user_id', $uid)->selectRaw("CONCAT(first_name,' ',last_name) AS name")->value('name');
            DB::table('users')->where('user_id', $uid)->update(['is_active' => 0]);
            ActivityLog::record($actorId, 'delete', 'user', "Deactivated user $uname", $uid);

            return ['type' => 'success', 'text' => "User <strong>$uname</strong> deactivated."];
        }

        if ($action === 'restore_db') {
            if (! $request->hasFile('sql_file') || ! $request->file('sql_file')->isValid()) {
                return ['type' => 'danger', 'text' => 'No file uploaded or upload error.'];
            }
            $file = $request->file('sql_file');
            if (strtolower($file->getClientOriginalExtension()) !== 'sql') {
                return ['type' => 'danger', 'text' => 'Only .sql files are allowed.'];
            }
            if ($request->input('confirm_phrase') !== 'RESTORE') {
                return ['type' => 'danger', 'text' => 'Restore not confirmed — type RESTORE exactly to proceed.'];
            }

            $sqlContent = file_get_contents($file->getRealPath());
            // A real dump always CREATEs at least one table — a .sql-named file that's actually
            // something else entirely (empty, plain text, HTML, a different tool's export)
            // shouldn't be handed to the mysql CLI/mysqli at all.
            if (! preg_match('/create\s+table/i', $sqlContent)) {
                return ['type' => 'danger', 'text' => 'This file doesn\'t look like a database dump (no CREATE TABLE statement found). Restore cancelled.'];
            }

            // Automatic safety net: snapshot the CURRENT database before overwriting it, so a
            // bad upload has a way back. Saved next to manual backups, not auto-deleted.
            $origName = $file->getClientOriginalName();
            $preRestoreName = 'pre_restore_' . now()->format('Y-m-d_His') . '.sql';
            $preRestoreDir = storage_path('app/backups');
            if (! is_dir($preRestoreDir)) {
                mkdir($preRestoreDir, 0755, true);
            }
            file_put_contents($preRestoreDir . DIRECTORY_SEPARATOR . $preRestoreName, DatabaseBackup::dump());

            $result = DatabaseBackup::restore($sqlContent);

            if ($result['success']) {
                ActivityLog::record($actorId, 'restore', 'database', "Restored database from uploaded file: $origName (pre-restore snapshot: $preRestoreName)");

                return ['type' => 'success', 'text' => 'Database restored successfully from ' . e($origName) . '. A snapshot of the previous state was saved as <strong>' . e($preRestoreName) . '</strong> in storage/app/backups.'];
            }

            ActivityLog::record($actorId, 'restore', 'database', "FAILED restore attempt from uploaded file: $origName — " . ($result['error'] ?? 'unknown error'));

            return ['type' => 'danger', 'text' => 'Restore failed: ' . e($result['error'] ?? 'Unknown error') . ' A pre-restore snapshot was saved as <strong>' . e($preRestoreName) . '</strong> in case anything changed.'];
        }

        return null;
    }
}
