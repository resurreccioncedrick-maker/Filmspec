<?php

namespace App\Support;

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
