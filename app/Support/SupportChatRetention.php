<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Shared by the support-chat:prune scheduled command and the Data Retention admin page's
 * "Run Cleanup Now" button, so both call exactly the same logic instead of forking it.
 */
class SupportChatRetention
{
    public static function retentionDays(): int
    {
        return (int) config('filmspec.support_chat_retention_days', 90);
    }

    /**
     * Counts for the admin page — how many messages exist, how many are currently past the
     * retention window (i.e. what a run right now would delete), and when cleanup last ran.
     */
    public static function stats(): array
    {
        $days = self::retentionDays();
        $cutoff = now()->subDays($days);

        return [
            'retention_days' => $days,
            'total_messages' => (int) DB::table('client_support_messages')->count(),
            'eligible_count' => (int) DB::table('client_support_messages')->where('created_at', '<', $cutoff)->count(),
            'eligible_with_attachment' => (int) DB::table('client_support_messages')
                ->where('created_at', '<', $cutoff)->whereNotNull('attachment_path')->count(),
            'last_run_at' => DB::table('system_settings')->where('setting_key', 'support_chat_last_prune_at')->value('setting_value'),
            'last_run_deleted' => DB::table('system_settings')->where('setting_key', 'support_chat_last_prune_count')->value('setting_value'),
        ];
    }

    /** Deletes messages (and their attachment files) past the retention window. Returns the count deleted. */
    public static function prune(?int $actorId = null): int
    {
        $days = self::retentionDays();
        $cutoff = now()->subDays($days);

        $old = DB::table('client_support_messages')
            ->where('created_at', '<', $cutoff)
            ->select('message_id', 'attachment_path')
            ->get();

        foreach ($old as $m) {
            if ($m->attachment_path && Storage::disk('local')->exists($m->attachment_path)) {
                Storage::disk('local')->delete($m->attachment_path);
            }
        }

        $deleted = $old->isEmpty() ? 0 : DB::table('client_support_messages')->where('created_at', '<', $cutoff)->delete();

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'support_chat_last_prune_at'],
            ['setting_value' => now()->toDateTimeString(), 'updated_by' => $actorId]
        );
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'support_chat_last_prune_count'],
            ['setting_value' => (string) $deleted, 'updated_by' => $actorId]
        );

        return $deleted;
    }
}
