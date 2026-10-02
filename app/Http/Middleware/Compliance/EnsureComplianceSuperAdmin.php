<?php

namespace App\Http\Middleware\Compliance;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureComplianceSuperAdmin
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        abort_unless(
            $user
            && method_exists(
                $user,
                'isSuperAdmin'
            )
            && $user->isSuperAdmin(),
            403,
            'Super Admin access required.'
        );

        return $next($request);
    }
}
