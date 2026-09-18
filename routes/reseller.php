<?php

use App\Http\Controllers\Reseller\DashboardController;
use App\Http\Controllers\Reseller\OperatorController;
use App\Http\Controllers\Reseller\NotificationController;
use App\Http\Controllers\Reseller\NetworkZoneController;
use App\Http\Controllers\Reseller\ManagerController;
use App\Http\Controllers\Reseller\CompanySettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
])
    ->prefix('reseller')
    ->name('reseller.')
    ->group(function (): void {
        Route::get(
            '/',
            DashboardController::class
        )->name(
            'dashboard'
        );

        Route::get(
            'notifications',
            [
                NotificationController::class,
                'index',
            ]
        )->name(
            'notifications.index'
        );

        Route::post(
            'notifications/read-all',
            [
                NotificationController::class,
                'readAll',
            ]
        )->name(
            'notifications.read-all'
        );

        Route::post(
            'notifications/{notification}/read',
            [
                NotificationController::class,
                'read',
            ]
        )->name(
            'notifications.read'
        );

        Route::get(
            'operators',
            [
                OperatorController::class,
                'index',
            ]
        )->name(
            'operators.index'
        );

        Route::post(
            'operators',
            [
                OperatorController::class,
                'store',
            ]
        )->name(
            'operators.store'
        );

        Route::get(
            'operators/{operator}/edit',
            [
                OperatorController::class,
                'edit',
            ]
        )->name(
            'operators.edit'
        );

        Route::put(
            'operators/{operator}',
            [
                OperatorController::class,
                'update',
            ]
        )->name(
            'operators.update'
        );

        Route::post(
            'operators/{operator}/toggle',
            [
                OperatorController::class,
                'toggle',
            ]
        )->name(
            'operators.toggle'
        );

        Route::delete(
            'operators/{operator}',
            [
                OperatorController::class,
                'destroy',
            ]
        )->name(
            'operators.destroy'
        );
    });

/*
 * Production Network Zone / Manager controls.
 */
Route::middleware([
    'auth',
])
    ->prefix('reseller')
    ->name('reseller.')
    ->group(function (): void {
        Route::get(
            'zones',
            [
                NetworkZoneController::class,
                'index',
            ]
        )->name(
            'zones.index'
        );

        Route::post(
            'zones',
            [
                NetworkZoneController::class,
                'store',
            ]
        )->name(
            'zones.store'
        );

        Route::put(
            'zones/{zone}',
            [
                NetworkZoneController::class,
                'update',
            ]
        )->name(
            'zones.update'
        );

        Route::post(
            'zones/{zone}/select',
            [
                NetworkZoneController::class,
                'select',
            ]
        )->name(
            'zones.select'
        );

        Route::patch(
            'zones/operators/{operator}',
            [
                NetworkZoneController::class,
                'assignOperator',
            ]
        )->name(
            'zones.operator'
        );

        Route::delete(
            'zones/{zone}',
            [
                NetworkZoneController::class,
                'destroy',
            ]
        )->name(
            'zones.destroy'
        );

        Route::get(
            'managers',
            [
                ManagerController::class,
                'index',
            ]
        )->name(
            'managers.index'
        );

        Route::post(
            'managers',
            [
                ManagerController::class,
                'store',
            ]
        )->name(
            'managers.store'
        );

        Route::post(
            'managers/{manager}/toggle',
            [
                ManagerController::class,
                'toggle',
            ]
        )->name(
            'managers.toggle'
        );

        Route::delete(
            'managers/{manager}',
            [
                ManagerController::class,
                'destroy',
            ]
        )->name(
            'managers.destroy'
        );
    });

/*
 * Reseller owner company profile.
 */
Route::middleware([
    'auth',
])
    ->prefix('reseller')
    ->name('reseller.')
    ->group(function (): void {
        Route::get(
            'company-settings',
            [
                CompanySettingsController::class,
                'index',
            ]
        )->name(
            'company-settings.index'
        );

        Route::put(
            'company-settings',
            [
                CompanySettingsController::class,
                'update',
            ]
        )->name(
            'company-settings.update'
        );
    });


/*
 * RESELLER_SELF_UPGRADE_ROUTE_V1
 */
\Illuminate\Support\Facades\Route::post(
    '/reseller/subscription/upgrade/{plan}',
    [
        \App\Http\Controllers\Reseller\SubscriptionController::class,
        'upgrade',
    ]
)
    ->middleware([
        'auth',
    ])
    ->name(
        'reseller.subscription.upgrade'
    );


/*
 * RESELLER_CLIENT_TRANSFER_ROUTES_V1
 *
 * Source-zone operator requests.
 * Destination-zone operator approves/rejects.
 */
\Illuminate\Support\Facades\Route::middleware([
    'auth',
])
    ->prefix(
        'reseller/transfers'
    )
    ->name(
        'reseller.transfers.'
    )
    ->group(function (): void {
        \Illuminate\Support\Facades\Route::get(
            '/',
            [
                \App\Http\Controllers\Reseller\ClientTransferController::class,
                'index',
            ]
        )->name(
            'index'
        );

        \Illuminate\Support\Facades\Route::post(
            '/',
            [
                \App\Http\Controllers\Reseller\ClientTransferController::class,
                'store',
            ]
        )->name(
            'store'
        );

        \Illuminate\Support\Facades\Route::post(
            '/{transfer}/approve',
            [
                \App\Http\Controllers\Reseller\ClientTransferController::class,
                'approve',
            ]
        )->name(
            'approve'
        );

        \Illuminate\Support\Facades\Route::post(
            '/{transfer}/reject',
            [
                \App\Http\Controllers\Reseller\ClientTransferController::class,
                'reject',
            ]
        )->name(
            'reject'
        );

        \Illuminate\Support\Facades\Route::post(
            '/{transfer}/cancel',
            [
                \App\Http\Controllers\Reseller\ClientTransferController::class,
                'cancel',
            ]
        )->name(
            'cancel'
        );
    });
