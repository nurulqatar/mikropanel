<?php

use App\Http\Controllers\Hotel\AuthController;
use App\Http\Controllers\Hotel\DashboardController;
use App\Http\Controllers\Hotel\SettingsController;
use App\Http\Controllers\Hotel\StaffController;
use App\Http\Controllers\SuperAdmin\Hotel\DashboardController as SuperAdminHotelDashboardController;
use App\Http\Controllers\SuperAdmin\Hotel\HotelController;
use App\Http\Controllers\SuperAdmin\Hotel\PlanController;
use App\Http\Middleware\HotelAuthenticate;
use App\Http\Middleware\ShareHotelContext;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| HOTEL HOTSPOT PRODUCT
|--------------------------------------------------------------------------
|
| Hotel Hotspot is intentionally isolated from the existing Company/ISP
| Hotspot module.
|
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
