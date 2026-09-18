<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Data Privacy Act (R.A. 10173) right-to-erasure. Shared by the client-facing request form
 * (HomeController) and the staff-side Data Retention page (DataRetentionController) so both
 * call exactly the same anonymization logic instead of forking it.
 *
 * Deliberately anonymizes rather than deletes: bookings, payments, and other financial/
 * operational records stay intact (needed for accounting/legal retention regardless of an
 * erasure request) — only the identifying fields on users/clients are scrubbed, and the
 * account is deactivated so it can no longer be logged into. Free-text history the client
 * wrote themselves (booking comments, support chat messages) is left as-is: rewriting a
 * booking's own communication record is a much larger, riskier operation than scrubbing a
 * profile, and those records may themselves need to be kept for the same legal reasons.
 */
class ClientErasure
{
    public static function pendingCount(): int
    {
        return (int) DB::table('data_erasure_requests')->where('status', 'pending')->count();
    }

    public static function pendingRequests()
    {
        return DB::table('data_erasure_requests as r')
            ->join('clients as c', 'r.client_id', '=', 'c.client_id')
            ->where('r.status', 'pending')
            ->orderBy('r.created_at')
            ->select('r.*', 'c.company_name', 'c.contact_person', 'c.email')
            ->get();
    }

    /** Anonymizes the client's identifying fields and deactivates their login. */
    public static function anonymize(int $clientId, int $processedBy, ?string $staffNotes = null): void
    {
        $client = DB::table('clients')->where('client_id', $clientId)->first();
        if (! $client) {
            return;
        }

        $placeholder = 'erased-client-' . $clientId . '@deleted.filmspec.local';

        DB::table('clients')->where('client_id', $clientId)->update([
            'company_name' => null, 'contact_person' => 'Erased User', 'email' => $placeholder,
            'phone' => null, 'address' => null, 'notes' => null, 'updated_at' => now(),
        ]);

        if ($client->user_id) {
            DB::table('users')->where('user_id', $client->user_id)->update([
                'first_name' => 'Erased', 'last_name' => 'User', 'email' => 'user-' . $client->user_id . '.' . $placeholder,
                'phone' => null, 'password_hash' => Hash::make(Str::random(40)), 'is_active' => 0, 'updated_at' => now(),
            ]);
        }

        DB::table('data_erasure_requests')->where('client_id', $clientId)->where('status', 'pending')->update([
            'status' => 'completed', 'processed_by' => $processedBy, 'processed_at' => now(),
            'staff_notes' => $staffNotes, 'updated_at' => now(),
        ]);
    }
}
