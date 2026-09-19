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
