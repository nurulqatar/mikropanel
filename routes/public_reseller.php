<?php

use App\Http\Controllers\PublicResellerRegistrationController;
use App\Http\Controllers\PublicWebsiteController;
use App\Http\Controllers\SuperAdmin\RegistrationRequestController;
use Illuminate\Support\Facades\Route;

Route::get(
    '/',
    [
        PublicWebsiteController::class,
        'index',
    ]
)->name(
    'website.home'
);

Route::get(
    '/reseller/register',
    [
        PublicResellerRegistrationController::class,
        'create',
    ]
)->name(
    'website.register'
);

Route::post(
    '/reseller/register',
    [
        PublicResellerRegistrationController::class,
        'store',
    ]
)
    ->middleware(
        'throttle:8,1'
    )
    ->name(
        'website.register.store'
    );

Route::get(
    '/reseller/register/status/{token}',
    [
        PublicResellerRegistrationController::class,
        'status',
    ]
)->name(
    'website.register.status'
);

Route::get(
    '/terms',
    [
        PublicWebsiteController::class,
        'terms',
    ]
)->name(
    'website.terms'
);

Route::get(
    '/privacy',
    [
        PublicWebsiteController::class,
        'privacy',
    ]
)->name(
    'website.privacy'
);

Route::middleware([
    'auth',
    'active.panel.user',
])
    ->prefix(
        'super-admin'
    )
    ->name(
        'superadmin.'
    )
    ->group(function (): void {
        Route::get(
            'registration-requests',
            [
                RegistrationRequestController::class,
                'index',
            ]
        )->name(
            'registrations.index'
        );

        Route::post(
            'registration-requests/{registration}/approve',
            [
                RegistrationRequestController::class,
                'approve',
            ]
        )->name(
            'registrations.approve'
        );

        Route::post(
            'registration-requests/{registration}/reject',
            [
                RegistrationRequestController::class,
                'reject',
            ]
        )->name(
            'registrations.reject'
        );
    });
