<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HotelPermission
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$permissions
    ): Response {
        $user =
            Auth::guard(
                'hotel'
            )->user();

        abort_unless(
            $user,
            401
        );

        if ($user->isAdmin()) {
            return $next(
                $request
            );
        }

        foreach (
            $permissions
            as $permission
        ) {
            if (
                $user->hasPermission(
                    $permission
                )
            ) {
                return $next(
                    $request
                );
            }
        }

        abort(
            403,
            'Hotel permission denied.'
        );
    }
}
