<?php

return [
    'groups' => [
        [
            'key' => 'dashboard',
            'label' => 'Dashboard',

            'permissions' => [
                'dashboard.view' =>
                    'View Dashboard',
            ],
        ],

        [
            'key' => 'clients',
            'label' => 'Clients',

            'permissions' => [
                'clients.view' =>
                    'View Clients',

                'clients.create' =>
                    'Add Clients',

                'clients.edit' =>
                    'Edit Clients',

                'clients.renew' =>
                    'Pay Due & Renew',

                'clients.suspend' =>
                    'Suspend & Activate',

                'clients.delete' =>
                    'Archive Clients',
            ],
        ],

        [
            'key' => 'routers',
            'label' => 'Routers',

            'permissions' => [
                'routers.view' =>
                    'View Routers',

                'routers.manage' =>
                    'Add, Edit, Sync & Delete Routers',
            ],
        ],

        [
            'key' => 'packages',
            'label' => 'Packages',

            'permissions' => [
                'packages.view' =>
                    'View Packages',

                'packages.manage' =>
                    'Add, Edit & Delete Packages',
            ],
        ],

        [
            'key' => 'ip_pools',
            'label' => 'IP Pools',

            'permissions' => [
                'ip_pools.view' =>
                    'View IP Pools',

                'ip_pools.manage' =>
                    'Add, Edit & Delete IP Pools',
            ],
        ],

        [
            'key' => 'invoices',
            'label' => 'Invoices',

            'permissions' => [
                'invoices.view' =>
                    'View Invoices',

                'invoices.manage' =>
                    'Create, Edit & Delete Invoices',

                'invoices.export' =>
                    'Print & Download Invoices',
            ],
        ],

        [
            'key' => 'payments',
            'label' => 'Payments',

            'permissions' => [
                'payments.view' =>
                    'View Payments',

                'payments.manage' =>
                    'Receive Payments',

                'payments.delete' =>
                    'Delete Payments',
            ],
        ],

        [
            'key' => 'expenses',
            'label' => 'Expenses',

            'permissions' => [
                'expenses.view' =>
                    'View Expenses',

                'expenses.manage' =>
                    'Add & Edit Expenses',

                'expenses.delete' =>
                    'Delete Expenses',
            ],
        ],

        [
            'key' => 'accounting',
            'label' => 'Accounting',

            'permissions' => [
                'accounting.view' =>
                    'View Accounting Reports',

                'accounting.export' =>
                    'Print & Download Reports',
            ],
        ],

        [
            'key' => 'settings',
            'label' => 'Settings',

            'permissions' => [
                'settings.manage' =>
                    'Manage Panel Settings',
            ],
        ],
    ],
];
