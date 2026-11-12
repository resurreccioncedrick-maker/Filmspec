<?php

return [

    'vat_rate' => (float) env('FILMSPEC_VAT_RATE', 0.12),

    'booking_prefix' => env('FILMSPEC_BOOKING_PREFIX', 'FS'),

    'company_tin' => env('FILMSPEC_COMPANY_TIN', '412-587-963-000'),

    'invoice_from' => [
        'name' => 'Film Spec Incorporated',
        'address' => "Unit 8 Southern Hills Residences\nPutatan 1772\nMuntinlupa City",
        'tin' => env('FILMSPEC_INVOICE_TIN', '603-308-544-00000'),
    ],

    'invoice_bank' => [
        'account_name' => 'Film Spec Incorporated',
        'account_number' => '0003 1003 7300',
        'bank' => 'Union Bank of the Philippines',
        'currency' => 'PHP',
    ],

    'support_chat_retention_days' => (int) env('FILMSPEC_SUPPORT_CHAT_RETENTION_DAYS', 90),

    'tc_version' => '1.0',
    'pp_version' => '1.0',

    'role_permissions' => [
        'super_admin'        => ['dashboard', 'equipment', 'accessories', 'crew', 'bookings', 'clients', 'billing', 'reports', 'users', 'activity', 'profile', 'superadmin', 'attendance', 'pos', 'incidents', 'transport', 'reminders', 'faqs', 'field_requests', 'repair_purchase', 'cost_estimates', 'crew_data', 'equipment_data', 'calendar_data', 'support_chat', 'data_retention'],
        'operations_manager' => ['dashboard', 'equipment', 'accessories', 'crew', 'bookings', 'clients', 'billing', 'reports', 'users', 'activity', 'profile', 'attendance', 'pos', 'incidents', 'transport', 'reminders', 'faqs', 'field_requests', 'repair_purchase', 'cost_estimates', 'crew_data', 'equipment_data', 'calendar_data', 'support_chat'],
        'traffic'            => ['dashboard', 'bookings', 'clients', 'crew', 'profile', 'attendance', 'incidents', 'transport', 'reminders', 'faqs', 'field_requests', 'repair_purchase', 'cost_estimates', 'crew_data', 'equipment_data', 'calendar_data', 'support_chat'],
        'accounting'         => ['dashboard', 'billing', 'reports', 'profile', 'reminders', 'faqs', 'cost_estimates', 'crew_data', 'equipment_data', 'calendar_data', 'bookings', 'support_chat'],
        'client'             => ['portal'],
        'crew'               => ['crew_portal'],
    ],

    'manage_roles' => ['super_admin', 'operations_manager'],

    'checklist_office_roles' => ['super_admin', 'operations_manager', 'traffic'],

    'mysqldump_path' => env('FILMSPEC_MYSQLDUMP_PATH'),
    'mysql_path' => env('FILMSPEC_MYSQL_PATH'),

    'all_staff' => ['super_admin', 'operations_manager', 'traffic', 'accounting'],

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
        'calendar_data' => 'calendar-data',
        'support_chat' => 'support-chat',
        'data_retention' => 'data-retention',
    ],

];
