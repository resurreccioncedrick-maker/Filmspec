<?php

namespace App\Support;

class StaffTeamLabel
{
    private const LABELS = [
        'super_admin' => 'Admin Team',
        'operations_manager' => 'Operations Team',
        'traffic' => 'Traffic Team',
        'accounting' => 'Accounting Team',
    ];

    public static function forRole(?string $roleName): string
    {
        return self::LABELS[$roleName] ?? 'Team';
    }
}
