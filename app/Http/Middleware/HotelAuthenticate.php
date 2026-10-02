<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HotelAuthenticate
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $guard =
            Auth::guard(
                'hotel'
            );

        if (!$guard->check()) {
            return redirect(
                '/hotel/login'
            );
        }

        $user =
            $guard->user();

        if (
            !$user
            || !$user->is_active
            || !$user->hotel
            || $user
                ->hotel
                ->status
                !== 'active'
        ) {
            $guard->logout();

            $request
                ->session()
                ->invalidate();

            $request
                ->session()
                ->regenerateToken();

            return redirect(
                '/hotel/login'
            );
        }

        return $next(
            $request
        );
    }
}
