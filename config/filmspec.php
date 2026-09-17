<?php

return [

    'vat_rate' => (float) env('FILMSPEC_VAT_RATE', 0.12),

    'booking_prefix' => env('FILMSPEC_BOOKING_PREFIX', 'FS'),

    // Placeholder BIR Tax Identification Number shown on printed receipts — swap for the
    // real registered TIN (format: 000-000-000-000, last 3 digits are the branch code,
    // 000 for head office) once available.
    'company_tin' => env('FILMSPEC_COMPANY_TIN', '412-587-963-000'),

    // How long a client support chat message (and its uploaded attachment file, if any) is
    // kept before support-chat:prune deletes it — see app/Console/Commands/PruneSupportChatMessages.php.
    'support_chat_retention_days' => (int) env('FILMSPEC_SUPPORT_CHAT_RETENTION_DAYS', 90),

    // Bump these manually whenever the Terms and Conditions / Privacy Policy text in
    // ce-preview.blade.php's #tcMo/#ppMo modals changes — recorded per-acceptance in
    // terms_acceptances so old rows keep showing what a client actually agreed to.
    'tc_version' => '1.0',
    'pp_version' => '1.0',

    // Mirrors core/auth.php ROLE_PERMISSIONS from the legacy app — module
    // names correspond to the pages being ported over one by one.
    'role_permissions' => [
        'super_admin'        => ['dashboard', 'equipment', 'accessories', 'crew', 'bookings', 'clients', 'billing', 'reports', 'users', 'activity', 'profile', 'superadmin', 'attendance', 'pos', 'incidents', 'transport', 'reminders', 'faqs', 'field_requests', 'repair_purchase', 'cost_estimates', 'crew_data', 'equipment_data', 'profit_loss', 'calendar_data', 'support_chat', 'data_retention'],
        'operations_manager' => ['dashboard', 'equipment', 'accessories', 'crew', 'bookings', 'clients', 'billing', 'reports', 'users', 'activity', 'profile', 'attendance', 'pos', 'incidents', 'transport', 'reminders', 'faqs', 'field_requests', 'repair_purchase', 'cost_estimates', 'crew_data', 'equipment_data', 'profit_loss', 'calendar_data', 'support_chat'],
        'traffic'            => ['dashboard', 'bookings', 'clients', 'crew', 'profile', 'attendance', 'incidents', 'transport', 'reminders', 'faqs', 'field_requests', 'repair_purchase', 'cost_estimates', 'crew_data', 'equipment_data', 'calendar_data', 'support_chat'],
        // Part B1: view-only bookings access for accounting — no mutating action in
        // BookingDetailController::act() grants the 'accounting' role, so this only ever
        // opens booking-detail read-only; it's what makes Cost Estimates' "view booking"
        // link actually work for them instead of dead-ending in a 403.
        'accounting'         => ['dashboard', 'billing', 'reports', 'profile', 'reminders', 'faqs', 'repair_purchase', 'cost_estimates', 'crew_data', 'equipment_data', 'profit_loss', 'calendar_data', 'bookings', 'support_chat'],
        'client'             => ['portal'],
        'crew'               => ['crew_portal'],
    ],

    'manage_roles' => ['super_admin', 'operations_manager'],

    // Optional explicit paths to the mysqldump/mysql CLI binaries, for Super Admin's
    // Database Backup/Restore. Left unset by default — DatabaseBackup looks the binaries
    // up on PATH first and only falls back to these (then to a pure-PHP dump/restore)
    // so nothing here ever hardcodes a version-specific install path that breaks on the
    // next MySQL upgrade.
    'mysqldump_path' => env('FILMSPEC_MYSQLDUMP_PATH'),
    'mysql_path' => env('FILMSPEC_MYSQL_PATH'),

    'all_staff' => ['super_admin', 'operations_manager', 'traffic', 'accounting'],

    // module slug => Laravel route name, used by layouts/app.blade.php's sidebar nav
    // ($pageUrl()/$curPage) to turn a nav item into a URL and highlight the active one.
    'ported_pages' => [
        'dashboard' => 'dashboard',
        'bookings' => 'bookings',
        'equipment' => 'equipment',
        'accessories' => 'accessories',
        'crew' => 'crew',
        'attendance' => 'attendance',
        'incidents' => 'incidents',
        'clients' => 'clients',
        'billing' => 'billing',
        'profile' => 'profile',
        'activity' => 'activity',
        'transport' => 'transport',
        'pos' => 'pos',
        'superadmin' => 'superadmin',
        'users' => 'users',
        'reports' => 'reports',
        'reminders' => 'reminders',
        'faqs' => 'faqs',
        'field_requests' => 'field-requests',
        'repair_purchase' => 'repair-purchase',
        'cost_estimates' => 'cost-estimates',
        'crew_data' => 'crew-data',
        'equipment_data' => 'equipment-data',
        'profit_loss' => 'profit-loss',
        'calendar_data' => 'calendar-data',
        'support_chat' => 'support-chat',
        'data_retention' => 'data-retention',
    ],

];
