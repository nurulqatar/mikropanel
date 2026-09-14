<?php

use App\Http\Controllers\Reseller\DashboardController;
use App\Http\Controllers\Reseller\OperatorController;
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
