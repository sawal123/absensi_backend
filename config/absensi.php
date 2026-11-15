<?php

return [
    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL', 'admin@absensi.com'),
        'password' => env('ADMIN_PASSWORD', ''),
    ],

    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
        'email' => env('SUPER_ADMIN_EMAIL', 'superadmin@absensi.com'),
        'password' => env('SUPER_ADMIN_PASSWORD', ''),
    ],

    'tenant' => [
        'name' => env('TENANT_NAME', 'Absensi'),
        'code' => env('TENANT_CODE', 'absensi'),
        'max_employees' => (int) env('TENANT_MAX_EMPLOYEES', 100),
    ],
];
