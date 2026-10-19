<?php

namespace App\Support;

/**
 * The client-facing departments a booking's (or the general support widget's) chat can be
 * routed to. Crew and super_admin deliberately have no department of their own here — crew have
 * no inbox for these messages at all (separate crew portal), and super_admin folds into 'staff'
 * since it shares the same general-admin relationship to a client as 'admin' does.
 */
class BookingChatDepartment
{
    public const LABELS = [
        'staff' => 'FilmSpec Staff',
        'accounting' => 'Accounting',
        'traffic' => 'Traffic',
        'operations_manager' => 'Operations Manager',
    ];

    private const ROLE_BUCKETS = [
        'accounting' => 'accounting',
        'traffic' => 'traffic',
        'operations_manager' => 'operations_manager',
    ];

    /** Which department bucket a STAFF reply auto-lands in, based on the replying user's own role. */
    public static function bucketForRole(?string $roleName): string
    {
        return self::ROLE_BUCKETS[(string) $roleName] ?? 'staff';
    }

    public static function label(?string $department): string
    {
        return self::LABELS[(string) $department] ?? self::LABELS['staff'];
    }

    public static function isValid(string $department): bool
    {
        return array_key_exists($department, self::LABELS);
    }
}
