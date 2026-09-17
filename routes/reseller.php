<?php

use App\Http\Controllers\Reseller\DashboardController;
use App\Http\Controllers\Reseller\OperatorController;
use App\Http\Controllers\Reseller\NotificationController;
use App\Http\Controllers\Reseller\NetworkZoneController;
use App\Http\Controllers\Reseller\ManagerController;
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
