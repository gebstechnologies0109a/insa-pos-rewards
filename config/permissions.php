<?php

/**
 * Role capabilities for INSA POS v3.
 * Super admin bypasses all checks in CheckRole middleware.
 */
return [
    'super_admin' => ['*'],

    'owner' => [
        'pos.cashier',
        'pos.settings',
        'inventory.batch.manage',
        'inventory.expiry.view',
        'inventory.forecast.view',
        'inventory.adjust',
        'inventory.movements.view',
        'stockman.audit',
        'admin.branches',
        'admin.users',
        'admin.products',
        'license.sessions.view',
    ],

    'admin' => [
        'pos.cashier',
        'pos.settings',
        'inventory.batch.manage',
        'inventory.expiry.view',
        'inventory.forecast.view',
        'inventory.adjust',
        'inventory.movements.view',
        'stockman.audit',
        'admin.branches',
        'admin.users',
        'admin.products',
        'license.sessions.view',
    ],

    'manager' => [
        'pos.cashier',
        'pos.settings.view',
        'inventory.batch.manage',
        'inventory.expiry.view',
        'inventory.forecast.view',
        'inventory.adjust',
        'inventory.movements.view',
        'stockman.audit',
        'admin.products',
        'license.sessions.view',
    ],

    'cashier' => [
        'pos.cashier',
    ],

    'stockman' => [
        'stockman.inventory',
        'stockman.audit',
        'inventory.batch.view',
        'inventory.expiry.view',
    ],
];
