<?php

namespace App\Http\Middleware\Compliance;

use App\Models\Compliance\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureComplianceSession
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $userId =
            $request->session()
                ->get(
                    'compliance_user_id'
                );

        $organizationId =
            $request->session()
                ->get(
                    'compliance_organization_id'
                );

        if (
            !$userId
            || !$organizationId
        ) {
            return redirect()
                ->route(
                    'compliance.login'
                );
        }

        $user =
            User::query()
                ->with('organization')
                ->whereKey($userId)
                ->where(
                    'organization_id',
                    $organizationId
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if (
            !$user
            || !$user->organization
            || $user->organization->status
                !== 'active'
        ) {
            $request
                ->session()
                ->forget([
                    'compliance_user_id',
                    'compliance_organization_id',
                ]);

            return redirect()
                ->route(
                    'compliance.login'
                );
        }

        $request->attributes->set(
            'complianceUser',
            $user
        );

        $request->attributes->set(
            'complianceOrganization',
            $user->organization
        );

        return $next($request);
    }
}
