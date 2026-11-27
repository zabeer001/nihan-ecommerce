<?php

return [
    'software_engineer_emails' => array_values(array_filter(array_map(
        static fn (string $email) => strtolower(trim($email)),
        explode(',', (string) env('SOFTWARE_ENGINEER_EMAILS', ''))
    ))),
    'system_roles' => ['user', 'editor', 'admin', 'superadmin'],
    'administrators' => ['admin', 'superadmin'],
    'permissions' => [
        'dashboard.view',
        'categories.view', 'categories.create', 'categories.update', 'categories.delete',
        'products.view', 'products.create', 'products.update', 'products.delete',
        'inventory.view', 'inventory.update',
        'orders.view', 'orders.create', 'orders.update',
        'homepage.manage',
        'storefront.manage',
        'roles.manage',
    ],
];
