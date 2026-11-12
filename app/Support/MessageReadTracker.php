<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MessageReadTracker
{
    public static function markRead(int $userId, string $threadType, int $threadId, $asOf = null): void
    {
        DB::table('staff_message_reads')->updateOrInsert(
            ['user_id' => $userId, 'thread_type' => $threadType, 'thread_id' => $threadId],
            ['last_read_at' => $asOf ?? now()]
        );
    }

    public static function unreadCount(int $userId, string $threadType, int $threadId, string $table, string $threadColumn): int
    {
        $lastRead = DB::table('staff_message_reads')
            ->where(['user_id' => $userId, 'thread_type' => $threadType, 'thread_id' => $threadId])
            ->value('last_read_at');

        $query = DB::table($table)->where($threadColumn, $threadId);

        return $lastRead ? $query->where('created_at', '>', $lastRead)->count() : $query->count();
    }

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
