<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off: the admin approve/reject UI is being removed since self-registered clients are now
 * auto-approved at email verification. Any client rows still sitting in status=pending from
 * before that change would otherwise be permanently stuck (blocked at login, with no UI left to
 * approve them) — converts them to approved. Deliberately rejected rows are left untouched.
 */
class CheckPendingClients extends Command
{
    protected $signature = 'clients:approve-pending-legacy';
    protected $description = 'One-off: approve any legacy pending clients before removing the approval UI';

    public function handle(): int
    {
        $pendingRows = DB::table('clients')->where('status', 'pending')->select('client_id', 'contact_person', 'email')->get();
        $this->info('Found ' . $pendingRows->count() . ' pending client(s).');
        foreach ($pendingRows as $row) {
            $this->line("  - #{$row->client_id} {$row->contact_person} <{$row->email}>");
        }

        if ($pendingRows->isNotEmpty()) {
            $updated = DB::table('clients')->where('status', 'pending')->update(['status' => 'approved']);
            $this->info("Approved $updated client(s).");
        }

        return 0;
    }
}
