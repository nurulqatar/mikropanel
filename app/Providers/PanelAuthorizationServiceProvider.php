<?php

namespace App\Providers;

use App\Http\Middleware\CheckPanelPermission;
use App\Http\Middleware\EnsurePanelUserIsActive;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class PanelAuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        app('router')->aliasMiddleware(
            'active.panel.user',
            EnsurePanelUserIsActive::class
        );

        app('router')->aliasMiddleware(
            'panel.permission',
            CheckPanelPermission::class
        );

        Inertia::share(
            'panelAuth',
            function (): array {
                $user = request()->user();

                if (!$user) {
                    return [
                        'user' => null,
                    ];
                }

                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,

                        'permissions' =>
                            $user->permissions
                            ?? [],

                        'is_active' =>
                            (bool) $user->is_active,
                    ],
                ];
            }
        );
    }
}
