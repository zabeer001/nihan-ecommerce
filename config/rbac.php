<?php

return [
    'system_roles' => ['user', 'editor', 'admin', 'superadmin'],
    'administrators' => ['admin', 'superadmin'],
    'permissions' => [
        'dashboard.view',
        'categories.view', 'categories.create', 'categories.update', 'categories.delete',
        'products.view', 'products.create', 'products.update', 'products.delete',
        'inventory.view', 'inventory.update',
        'orders.view', 'orders.create', 'orders.update',
        'roles.manage',
    ],
];
