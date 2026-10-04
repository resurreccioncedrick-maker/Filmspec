<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixClientApprovalStatus extends Command
{
    protected $signature = 'fix:client-approval-status';

    protected $description = 'One-off repair for bookings created via client self-service (CartController::submitBooking) that never explicitly set approval_status before the fix, so they silently fell back to the column default (approved) instead of pending_approval — making them invisible in the Bookings > Awaiting Review queue despite the client being told their booking was pending admin approval.';

    public function handle(): int
    {
        $victims = DB::table('bookings as b')
            ->join('users as u', 'b.created_by', '=', 'u.user_id')
            ->join('roles as r', 'u.role_id', '=', 'r.role_id')
            ->where('r.role_name', 'client')
            ->where('b.approval_status', 'approved')
            ->whereNull('b.approved_by')
            ->get(['b.booking_id', 'b.booking_reference', 'b.project_title', 'b.booking_status', 'b.created_at']);

        $this->info('Found ' . $victims->count() . ' affected booking(s):');
        foreach ($victims as $v) {
            $this->line(json_encode($v));
        }

        if ($victims->isNotEmpty()) {
            $ids = $victims->pluck('booking_id')->all();
            DB::table('bookings')->whereIn('booking_id', $ids)->update(['approval_status' => 'pending_approval']);
            $this->info('Corrected ' . count($ids) . ' booking(s) to approval_status=pending_approval.');
        }

        return self::SUCCESS;
    }
}
