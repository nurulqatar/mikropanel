<?php

use App\Http\Controllers\Compliance\AuthController;
use App\Http\Controllers\Compliance\DashboardController;
use App\Http\Controllers\Compliance\NetworkController;
use App\Http\Controllers\Compliance\RouterController;
use App\Http\Controllers\SuperAdmin\Compliance\DashboardController as SuperAdminComplianceDashboardController;
use App\Http\Middleware\Compliance\EnsureComplianceSession;
use App\Http\Middleware\Compliance\EnsureComplianceSuperAdmin;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Standalone Compliance Portal
|--------------------------------------------------------------------------
*/

Route::get(
    '/compliance/login',
    [
        AuthController::class,
        'create',
    ]
)->name('compliance.login');

Route::post(
    '/compliance/login',
    [
        AuthController::class,
        'store',
    ]
)->name('compliance.login.store');

Route::middleware(
    EnsureComplianceSession::class
)
    ->prefix('compliance')
    ->name('compliance.')
    ->group(
        function (): void {
            Route::get(
                '/',
                DashboardController::class
            )->name('dashboard');

            Route::post(
                '/logout',
                [
                    AuthController::class,
                    'destroy',
                ]
            )->name('logout');

            Route::get(
                '/networks',
                [
                    NetworkController::class,
                    'index',
                ]
            )->name(
                'networks.index'
            );

            Route::post(
                '/networks',
                [
                    NetworkController::class,
                    'store',
                ]
            )->name(
                'networks.store'
            );

            Route::get(
                '/routers',
                [
                    RouterController::class,
                    'index',
                ]
            )->name(
                'routers.index'
            );

            Route::post(
                '/routers',
                [
                    RouterController::class,
                    'store',
                ]
            )->name(
                'routers.store'
            );
        }
    );

/*
|--------------------------------------------------------------------------
| Super Admin Compliance Control
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
    EnsureComplianceSuperAdmin::class,
])
    ->prefix(
        'super-admin/compliance'
    )
    ->name(
        'superadmin.compliance.'
    )
    ->group(
        function (): void {
            Route::get(
                '/',
                [
                    SuperAdminComplianceDashboardController::class,
                    'index',
                ]
            )->name(
                'dashboard'
            );

            Route::post(
                '/plans',
                [
                    SuperAdminComplianceDashboardController::class,
                    'storePlan',
                ]
            )->name(
                'plans.store'
            );

            Route::post(
                '/organizations',
                [
                    SuperAdminComplianceDashboardController::class,
                    'storeOrganization',
                ]
            )->name(
                'organizations.store'
            );

            Route::post(
                '/subscriptions',
                [
                    SuperAdminComplianceDashboardController::class,
                    'storeSubscription',
                ]
            )->name(
                'subscriptions.store'
            );

            Route::post(
                '/access-grants',
                [
                    SuperAdminComplianceDashboardController::class,
                    'storeGrant',
                ]
            )->name(
                'grants.store'
            );
        }
    );
