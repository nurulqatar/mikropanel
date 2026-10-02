<?php

use App\Http\Controllers\Reseller\ManagerCashController;
use App\Http\Middleware\EnsurePanelUserIsActive;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth',
    EnsurePanelUserIsActive::class,
])
    ->prefix('reseller/cash')
    ->name('reseller.cash.')
    ->group(function (): void {
        Route::get(
            '/',
            [
                ManagerCashController::class,
                'index',
            ]
        )->name(
            'index'
        );

        Route::post(
            'handovers',
            [
                ManagerCashController::class,
                'storeHandover',
            ]
        )->name(
            'handovers.store'
        );

        Route::post(
            'handovers/{handover}/approve',
            [
                ManagerCashController::class,
                'approveHandover',
            ]
        )->name(
            'handovers.approve'
        );

        Route::post(
            'handovers/{handover}/reject',
            [
                ManagerCashController::class,
                'rejectHandover',
            ]
        )->name(
            'handovers.reject'
        );

        Route::post(
            'expenses/{expense}/reject',
            [
                ManagerCashController::class,
                'rejectExpense',
            ]
        )->name(
            'expenses.reject'
        );
    });
