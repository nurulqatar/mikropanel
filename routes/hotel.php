<?php

use App\Http\Controllers\Hotel\AuthController;
use App\Http\Controllers\Hotel\DashboardController;
use App\Http\Controllers\Hotel\GuestController;
use App\Http\Controllers\Hotel\PortalController;
use App\Http\Controllers\Hotel\RouterController;
use App\Http\Controllers\Hotel\SettingsController;
use App\Http\Controllers\Hotel\StaffController;
use App\Http\Controllers\Hotel\VoucherController;
use App\Http\Controllers\SuperAdmin\Hotel\DashboardController as SuperAdminHotelDashboardController;
use App\Http\Controllers\SuperAdmin\Hotel\HotelController;
use App\Http\Controllers\SuperAdmin\Hotel\PlanController;
use App\Http\Middleware\HotelAuthenticate;
use App\Http\Middleware\ShareHotelContext;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Hotel Guest WiFi Portal
|--------------------------------------------------------------------------
*/

Route::prefix('wifi/{hotel:slug}')
    ->name('hotel.portal.')
    ->group(function (): void {
        Route::get(
            '/',
            [
                PortalController::class,
                'welcome',
            ]
        )->name(
            'welcome'
        );

        Route::get(
            'access',
            [
                PortalController::class,
                'access',
            ]
        )->name(
            'access'
        );

        Route::get(
            'register',
            [
                PortalController::class,
                'register',
            ]
        )->name(
            'register'
        );

        Route::post(
            'register',
            [
                PortalController::class,
                'store',
            ]
        )
            ->middleware(
                'throttle:12,1'
            )
            ->name(
                'register.store'
            );

        Route::get(
            'voucher/{token}',
            [
                PortalController::class,
                'voucher',
            ]
        )->name(
            'voucher'
        );

        Route::get(
            'login',
            [
                PortalController::class,
                'login',
            ]
        )->name(
            'login'
        );

        Route::post(
            'login',
            [
                PortalController::class,
                'verify',
            ]
        )
            ->middleware(
                'throttle:20,1'
            )
            ->name(
                'login.verify'
            );
    });

/*
|--------------------------------------------------------------------------
| Super Admin Hotel Hotspot
|--------------------------------------------------------------------------
*/

Route::middleware([
    'auth',
])
    ->prefix(
        'super-admin/hotel-hotspot'
    )
    ->name(
        'superadmin.hotel.'
    )
    ->group(function (): void {
        Route::get(
            '/',
            SuperAdminHotelDashboardController::class
        )->name(
            'dashboard'
        );

        Route::get(
            'plans',
            [
                PlanController::class,
                'index',
            ]
        )->name(
            'plans.index'
        );

        Route::post(
            'plans',
            [
                PlanController::class,
                'store',
            ]
        )->name(
            'plans.store'
        );

        Route::put(
            'plans/{plan}',
            [
                PlanController::class,
                'update',
            ]
        )->name(
            'plans.update'
        );

        Route::delete(
            'plans/{plan}',
            [
                PlanController::class,
                'destroy',
            ]
        )->name(
            'plans.destroy'
        );

        Route::get(
            'hotels',
            [
                HotelController::class,
                'index',
            ]
        )->name(
            'hotels.index'
        );

        Route::get(
            'hotels/create',
            [
                HotelController::class,
                'create',
            ]
        )->name(
            'hotels.create'
        );

        Route::post(
            'hotels',
            [
                HotelController::class,
                'store',
            ]
        )->name(
            'hotels.store'
        );

        Route::get(
            'hotels/{hotel}/edit',
            [
                HotelController::class,
                'edit',
            ]
        )->name(
            'hotels.edit'
        );

        Route::put(
            'hotels/{hotel}',
            [
                HotelController::class,
                'update',
            ]
        )->name(
            'hotels.update'
        );

        Route::post(
            'hotels/{hotel}/subscription',
            [
                HotelController::class,
                'subscription',
            ]
        )->name(
            'hotels.subscription'
        );
    });

/*
|--------------------------------------------------------------------------
| Hotel Admin / Receptionist
|--------------------------------------------------------------------------
*/

Route::prefix('hotel')
    ->name('hotel.')
    ->group(function (): void {
        Route::get(
            'login',
            [
                AuthController::class,
                'create',
            ]
        )->name(
            'login'
        );

        Route::post(
            'login',
            [
                AuthController::class,
                'store',
            ]
        )
            ->middleware(
                'throttle:8,1'
            )
            ->name(
                'login.store'
            );

        Route::middleware([
            HotelAuthenticate::class,
            ShareHotelContext::class,
        ])->group(function (): void {
            Route::get(
                '/',
                DashboardController::class
            )->name(
                'dashboard'
            );

            Route::post(
                'logout',
                [
                    AuthController::class,
                    'destroy',
                ]
            )->name(
                'logout'
            );

            Route::get(
                'guests',
                [
                    GuestController::class,
                    'index',
                ]
            )->name(
                'guests.index'
            );

            Route::get(
                'vouchers',
                [
                    VoucherController::class,
                    'index',
                ]
            )->name(
                'vouchers.index'
            );

            Route::get(
                'vouchers/create',
                [
                    VoucherController::class,
                    'create',
                ]
            )->name(
                'vouchers.create'
            );

            Route::post(
                'vouchers',
                [
                    VoucherController::class,
                    'store',
                ]
            )->name(
                'vouchers.store'
            );

            Route::get(
                'vouchers/{voucher}/print',
                [
                    VoucherController::class,
                    'print',
                ]
            )->name(
                'vouchers.print'
            );

            /*
             * HOTEL_HOTSPOT_REPORTING_V4
             */
            Route::get(
                'reports',
                [
                    \App\Http\Controllers\Hotel\ReportController::class,
                    'index',
                ]
            )->name(
                'reports.index'
            );

            Route::get(
                'reports/export.csv',
                [
                    \App\Http\Controllers\Hotel\ReportController::class,
                    'csv',
                ]
            )->name(
                'reports.csv'
            );

            /*
             * HOTEL_MIKROTIK_ROUTES_V1
             */
            Route::get(
                'routers',
                [
                    RouterController::class,
                    'index',
                ]
            )->name(
                'routers.index'
            );

            Route::post(
                'routers',
                [
                    RouterController::class,
                    'store',
                ]
            )->name(
                'routers.store'
            );

            Route::put(
                'routers/{router}',
                [
                    RouterController::class,
                    'update',
                ]
            )->name(
                'routers.update'
            );

            Route::delete(
                'routers/{router}',
                [
                    RouterController::class,
                    'destroy',
                ]
            )->name(
                'routers.destroy'
            );

            Route::post(
                'routers/{router}/test',
                [
                    RouterController::class,
                    'test',
                ]
            )->name(
                'routers.test'
            );

            Route::get(
                'staff',
                [
                    StaffController::class,
                    'index',
                ]
            )->name(
                'staff.index'
            );

            Route::post(
                'staff',
                [
                    StaffController::class,
                    'store',
                ]
            )->name(
                'staff.store'
            );

            Route::put(
                'staff/{staff}',
                [
                    StaffController::class,
                    'update',
                ]
            )->name(
                'staff.update'
            );

            Route::delete(
                'staff/{staff}',
                [
                    StaffController::class,
                    'destroy',
                ]
            )->name(
                'staff.destroy'
            );

            Route::get(
                'settings',
                [
                    SettingsController::class,
                    'index',
                ]
            )->name(
                'settings.index'
            );

            Route::post(
                'settings',
                [
                    SettingsController::class,
                    'update',
                ]
            )->name(
                'settings.update'
            );
        });
    });
