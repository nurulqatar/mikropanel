<?php

use App\Http\Controllers\SuperAdmin\DashboardController;
use App\Http\Controllers\SuperAdmin\ResellerController;
use App\Http\Controllers\SuperAdmin\ResellerPlanController;
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
