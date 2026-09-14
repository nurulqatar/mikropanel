<?php

return array (
  'groups' =>
  array (
    0 =>
    array (
      'key' => 'dashboard',
      'label' => 'Dashboard',
      'permissions' =>
      array (
        'dashboard.view' => 'View Dashboard & Quick Desk',
      ),
    ),
    1 =>
    array (
      'key' => 'clients',
      'label' => 'Clients',
      'permissions' =>
      array (
        'clients.view' => 'View Clients & Custom Information',
        'clients.create' => 'Add Clients',
        'clients.edit' => 'Edit Clients & Custom Information',
        'clients.renew' => 'Pay Due & Renew',
        'clients.suspend' => 'Suspend & Activate',
        'clients.delete' => 'Archive/Delete Clients',
      ),
    ),
    2 =>
    array (
      'key' => 'routers',
      'label' => 'Routers',
      'permissions' =>
      array (
        'routers.view' => 'View Routers',
        'routers.manage' => 'Add, Edit, Ping, Sync & Delete Routers',
      ),
    ),
    3 =>
    array (
      'key' => 'packages',
      'label' => 'Packages',
      'permissions' =>
      array (
        'packages.view' => 'View Packages',
        'packages.manage' => 'Add, Edit & Delete Packages',
      ),
    ),
    4 =>
    array (
      'key' => 'ip_pools',
      'label' => 'IP Pools',
      'permissions' =>
      array (
        'ip_pools.view' => 'View Global IP Pools',
        'ip_pools.manage' => 'Add, Edit & Delete Global IP Pools',
      ),
    ),
    5 =>
    array (
      'key' => 'invoices',
      'label' => 'Invoices',
      'permissions' =>
      array (
        'invoices.view' => 'View Invoices',
        'invoices.manage' => 'Create, Edit & Delete Invoices',
        'invoices.export' => 'Print & Download Invoices',
      ),
    ),
    6 =>
    array (
      'key' => 'payments',
      'label' => 'Payments',
      'permissions' =>
      array (
        'payments.view' => 'View Payment History',
        'payments.manage' => 'Receive Payments',
        'payments.delete' => 'Delete Payments',
      ),
    ),
    7 =>
    array (
      'key' => 'expenses',
      'label' => 'Expenses',
      'permissions' =>
      array (
        'expenses.view' => 'View Expenses',
        'expenses.manage' => 'Add & Edit Expenses',
        'expenses.delete' => 'Delete Expenses',
      ),
    ),
    8 =>
    array (
      'key' => 'accounting',
      'label' => 'Accounting & Reports',
      'permissions' =>
      array (
        'accounting.view' => 'View Accounting & Client Reports',
        'accounting.export' => 'Print & Download Reports',
      ),
    ),
    9 =>
    array (
      'key' => 'notifications',
      'label' => 'Notifications',
      'permissions' =>
      array (
        'notifications.view' => 'View Notifications',
        'notifications.manage' => 'Manage Notification Status & Actions',
      ),
    ),
    10 =>
    array (
      'key' => 'settings',
      'label' => 'Settings',
      'permissions' =>
      array (
        'settings.manage' => 'Manage Settings & Client Form Builder',
      ),
    ),
    11 =>
    array (
      'key' => 'hotspot',
      'label' => 'Hotspot',
      'permissions' =>
      array (
        'hotspot.view' => 'View Hotspot Dashboard, Vouchers & Reports',
        'hotspot.manage' => 'Manage Hotspot Plans, Vouchers, Sessions & Branding',
        'hotspot.sell' => 'Sell Hotspot Vouchers',
        'hotspot.payments' => 'Receive Hotspot Due Payments',
        'hotspot.export' => 'Print, PDF & Export Hotspot Data',
      ),
    ),
  ),
);
