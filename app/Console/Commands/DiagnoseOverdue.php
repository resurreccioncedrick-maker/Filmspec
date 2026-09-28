<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only diagnostic for the Billing "Overdue" count-vs-list mismatch — prints every
 * statement_of_accounts row that matches the overdue predicate (due_date < today, status !=
 * paid, balance > 0) alongside whether its booking_id/client_id actually joins to a real row,
 * since filteredOverdueQuery() inner-joins bookings/clients and would silently drop an
 * orphaned reference that the plain count query (no join) still counts.
 */
class DiagnoseOverdue extends Command
{
    protected $signature = 'billing:diagnose-overdue {--delete-orphans : Delete SOA rows whose booking_id no longer exists in bookings}';

    protected $description = 'Show which overdue SOA rows exist, whether they join to bookings/clients, and optionally delete orphans';

    public function handle(): int
    {
        $today = now()->toDateString();
        $rows = DB::table('statement_of_accounts')
            ->where('due_date', '<', $today)
            ->where('status', '!=', 'paid')
            ->where('balance', '>', 0)
            ->get();

        $this->info("Today: $today");
        $this->info('Rows matching the overdue predicate (no join): ' . $rows->count());

        $orphanIds = [];
        foreach ($rows as $r) {
            $booking = DB::table('bookings')->where('booking_id', $r->booking_id)->first();
            $client = $booking ? DB::table('clients')->where('client_id', $booking->client_id)->first() : null;
            if (! $booking || ! $client) {
                $orphanIds[] = $r->soa_id;
            }

            $this->line('---');
            $this->line("soa_id={$r->soa_id} ref={$r->soa_reference} status={$r->status} balance={$r->balance} due={$r->due_date} booking_id={$r->booking_id}");
            $this->line('  booking exists: ' . ($booking ? 'YES (status=' . $booking->booking_status . ')' : 'NO — ORPHANED'));
            $this->line('  client exists: ' . ($client ? 'YES' : ($booking ? 'NO — ORPHANED' : 'n/a')));
        }

        $joined = DB::table('statement_of_accounts as s')
            ->join('bookings as b', 's.booking_id', '=', 'b.booking_id')
            ->join('clients as c', 'b.client_id', '=', 'c.client_id')
            ->where('s.due_date', '<', $today)
            ->where('s.status', '!=', 'paid')
            ->where('s.balance', '>', 0)
            ->count();
        $this->info("Rows that survive the same predicate WITH the inner joins: $joined");

        if ($orphanIds) {
            $this->warn('Orphaned soa_ids (booking or client no longer exists): ' . implode(', ', $orphanIds));
            if ($this->option('delete-orphans')) {
                DB::table('statement_of_accounts')->whereIn('soa_id', $orphanIds)->delete();
                $this->info('Deleted ' . count($orphanIds) . ' orphaned statement_of_accounts row(s).');
            } else {
                $this->line('Re-run with --delete-orphans to remove them.');
            }
        }

        return self::SUCCESS;
    }
}
