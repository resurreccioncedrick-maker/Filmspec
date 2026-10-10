<?php

namespace App\Support;

/**
 * Display label for "who on the team" replied, shown in the client-facing support chat as
 * "FilmSpec ({label})" — distinct from SuperAdminController's $rolesDef, which labels roles
 * for the internal Role Access Matrix, not for client-facing attribution.
 */
class StaffTeamLabel
{
    private const LABELS = [
        'super_admin' => 'Admin Team',
        'admin' => 'Staff',
        'operations_manager' => 'Operations Team',
        'traffic' => 'Traffic Team',
        'accounting' => 'Accounting Team',
    ];

    public static function forRole(?string $roleName): string
    {
        return self::LABELS[$roleName] ?? 'Team';
    }
}
