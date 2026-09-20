<?php

use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\ResellerController;
use App\Http\Controllers\SuperAdmin\ResellerFinanceController;
use App\Http\Controllers\SuperAdmin\ResellerPlanController;
use App\Http\Controllers\SuperAdmin\WebsiteSettingsController;
use App\Http\Controllers\SuperAdmin\ResellerReportController;
use App\Http\Controllers\SuperAdmin\ResellerAuditController;
use App\Http\Controllers\SuperAdmin\ResellerNotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
])
    ->prefix('super-admin')
    ->name('superadmin.')
    ->group(function (): void {
        Route::get(
            '/',
            DashboardController::class
        )->name(
            'dashboard'
        );

        /*
         * SUPER_ADMIN_WEBSITE_SETTINGS_V1
         */
        Route::get(
            'website-settings',
            [
                WebsiteSettingsController::class,
                'index',
            ]
        )->name(
            'website-settings.index'
        );

        Route::post(
            'website-settings',
            [
                WebsiteSettingsController::class,
                'update',
            ]
        )->name(
            'website-settings.update'
        );

        Route::delete(
            'website-settings/logo',
            [
                WebsiteSettingsController::class,
                'removeLogo',
            ]
        )->name(
            'website-settings.logo.destroy'
        );

        Route::get(
            'reseller-plans',
            [
                ResellerPlanController::class,
                'index',
            ]
        )->name(
            'plans.index'
        );

        Route::post(
            'reseller-plans',
            [
                ResellerPlanController::class,
                'store',
            ]
        )->name(
            'plans.store'
        );

        Route::put(
            'reseller-plans/{plan}',
            [
                ResellerPlanController::class,
                'update',
            ]
        )->name(
            'plans.update'
        );

        Route::delete(
            'reseller-plans/{plan}',
            [
                ResellerPlanController::class,
                'destroy',
            ]
        )->name(
            'plans.destroy'
        );

        Route::get(
            'reports',
            [
                ResellerReportController::class,
                'index',
            ]
        )->name(
            'reports.index'
        );

        Route::get(
            'reports/csv',
            [
                ResellerReportController::class,
                'csv',
            ]
        )->name(
            'reports.csv'
        );

        Route::get(
            'audit',
            [
                ResellerAuditController::class,
                'index',
            ]
        )->name(
            'audit.index'
        );

        Route::get(
            'notifications',
            [
                ResellerNotificationController::class,
                'index',
            ]
        )->name(
            'notifications.index'
        );

        Route::post(
            'notifications/read-all',
            [
                ResellerNotificationController::class,
                'readAll',
            ]
        )->name(
            'notifications.read-all'
        );

        Route::post(
            'notifications/{notification}/read',
            [
                ResellerNotificationController::class,
                'read',
            ]
        )->name(
            'notifications.read'
        );

        Route::get(
            'wallet',
            [
                ResellerFinanceController::class,
                'ledger',
            ]
        )->name(
            'wallet.index'
        );

        Route::get(
            'recharges',
            [
                ResellerFinanceController::class,
                'recharges',
            ]
        )->name(
            'recharges.index'
        );

        Route::get(
            'resellers',
            [
                ResellerController::class,
                'index',
            ]
        )->name(
            'resellers.index'
        );

        Route::get(
            'resellers/create',
            [
                ResellerController::class,
                'create',
            ]
        )->name(
            'resellers.create'
        );

        Route::post(
            'resellers',
            [
                ResellerController::class,
                'store',
            ]
        )->name(
            'resellers.store'
        );

        Route::get(
            'resellers/{reseller}',
            [
                ResellerController::class,
                'show',
            ]
        )->name(
            'resellers.show'
        );

        Route::get(
            'resellers/{reseller}/edit',
            [
                ResellerController::class,
                'edit',
            ]
        )->name(
            'resellers.edit'
        );

        Route::put(
            'resellers/{reseller}',
            [
                ResellerController::class,
                'update',
            ]
        )->name(
            'resellers.update'
        );

        Route::post(
            'resellers/{reseller}/wallet/recharge',
            [
                ResellerFinanceController::class,
                'recharge',
            ]
        )->name(
            'resellers.wallet.recharge'
        );

        Route::post(
            'resellers/{reseller}/wallet/deduct',
            [
                ResellerFinanceController::class,
                'deduct',
            ]
        )->name(
            'resellers.wallet.deduct'
        );

        Route::post(
            'resellers/{reseller}/wallet/adjustment',
            [
                ResellerFinanceController::class,
                'adjustment',
            ]
        )->name(
            'resellers.wallet.adjustment'
        );

        Route::post(
            'resellers/{reseller}/suspend',
            [
                ResellerController::class,
                'suspend',
            ]
        )->name(
            'resellers.suspend'
        );

        Route::post(
            'resellers/{reseller}/reactivate',
            [
                ResellerController::class,
                'reactivate',
            ]
        )->name(
            'resellers.reactivate'
        );

        Route::post(
            'resellers/{reseller}/renew',
            [
                ResellerController::class,
                'renew',
            ]
        )->name(
            'resellers.renew'
        );

        Route::post(
            'resellers/{reseller}/change-plan',
            [
                ResellerController::class,
                'changePlan',
            ]
        )->name(
            'resellers.change-plan'
        );
    });

/* RENTAL_MASTER_PLATFORM_V1 */
\Illuminate\Support\Facades\Route::middleware(['auth'])
    ->prefix('super-admin/rentals')
    ->name('superadmin.rentals.')
    ->group(function (): void {
        $c = \App\Http\Controllers\SuperAdmin\RentalManagementController::class;

        \Illuminate\Support\Facades\Route::get('/', [$c, 'index'])->name('index');
        \Illuminate\Support\Facades\Route::post('/sync', [$c, 'sync'])->name('sync');
        \Illuminate\Support\Facades\Route::get('/export.csv', [$c, 'export'])->name('export');
        \Illuminate\Support\Facades\Route::get('/invoices/{invoice}/print', [$c, 'printInvoice'])->name('invoices.print');
        \Illuminate\Support\Facades\Route::post('/invoices/{invoice}/payments', [$c, 'payment'])->name('payments.store');
        \Illuminate\Support\Facades\Route::put('/tickets/{ticket}', [$c, 'ticketStatus'])->name('tickets.update');
        \Illuminate\Support\Facades\Route::get('/{contract}', [$c, 'show'])->name('show');
        \Illuminate\Support\Facades\Route::put('/{contract}', [$c, 'update'])->name('update');
        \Illuminate\Support\Facades\Route::post('/{contract}/invoices', [$c, 'invoice'])->name('invoices.store');
        \Illuminate\Support\Facades\Route::post('/{contract}/tickets', [$c, 'ticket'])->name('tickets.store');
        \Illuminate\Support\Facades\Route::post('/{contract}/cancel', [$c, 'cancel'])->name('cancel');
    });
