<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared per-user read/unread tracking for the three staff messaging surfaces (support chat,
 * booking comments, repair/purchase tickets) — see staff_message_reads migration. One small
 * table instead of a read-state column duplicated in each message table.
 */
class MessageReadTracker
{
    /**
     * @param  \DateTimeInterface|string|null  $asOf  the newest message's own created_at, not
     *   wall-clock now() — created_at columns across these tables are second-precision
     *   datetimes, so marking read with now() can collide with a message inserted the same
     *   second and permanently hide it as unread. Recording "read through the newest message
     *   that existed" instead avoids that in the common case. Falls back to now() when the
     *   thread has no messages yet (nothing to be "as of").
     */
    public static function markRead(int $userId, string $threadType, int $threadId, $asOf = null): void
    {
        DB::table('staff_message_reads')->updateOrInsert(
            ['user_id' => $userId, 'thread_type' => $threadType, 'thread_id' => $threadId],
            ['last_read_at' => $asOf ?? now()]
        );
    }

    /**
     * Unread count for a single thread — messages after this user's last_read_at, or every
     * message if they've never opened it.
     */
    public static function unreadCount(int $userId, string $threadType, int $threadId, string $table, string $threadColumn): int
    {
        $lastRead = DB::table('staff_message_reads')
            ->where(['user_id' => $userId, 'thread_type' => $threadType, 'thread_id' => $threadId])
            ->value('last_read_at');

        $query = DB::table($table)->where($threadColumn, $threadId);

        return $lastRead ? $query->where('created_at', '>', $lastRead)->count() : $query->count();
    }

    /**
     * Unread counts for many threads at once (e.g. every conversation in an inbox list) —
     * one read-markers query instead of N, keyed by thread_id.
     *
     * @param  Collection<int, object{thread_id: int, created_at: string}>  $latestMessagePerThread  each thread's most recent message row (must include thread_id + created_at)
     * @return array<int, bool> thread_id => has unread messages
     */
    public static function unreadFlags(int $userId, string $threadType, Collection $latestMessagePerThread): array
    {
        $threadIds = $latestMessagePerThread->pluck('thread_id')->all();
        if (! $threadIds) {
            return [];
        }

        $lastReads = DB::table('staff_message_reads')
            ->where('user_id', $userId)->where('thread_type', $threadType)
            ->whereIn('thread_id', $threadIds)
            ->pluck('last_read_at', 'thread_id');

        $flags = [];
        foreach ($latestMessagePerThread as $row) {
            $lastRead = $lastReads->get($row->thread_id);
            $flags[$row->thread_id] = ! $lastRead || $row->created_at > $lastRead;
        }

        return $flags;
    }
}
