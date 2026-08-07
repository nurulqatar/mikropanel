<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPanelPermission
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (!$user || $user->isAdmin()) {
            return $next($request);
        }

        $routeName = $request
            ->route()
            ?->getName();

        $permission = $this->permissionForRoute(
            $routeName
        );

        if (
            $permission
            && !$user->hasPermission(
                $permission
            )
        ) {
            abort(
                403,
                'You do not have permission to perform this action.'
            );
        }

        return $next($request);
    }

    private function permissionForRoute(
        ?string $routeName
    ): ?string {
        if (!$routeName) {
            return null;
        }

        return match (true) {
            $routeName === 'dashboard' =>
                'dashboard.view',

            in_array(
                $routeName,
                [
                    'clients.index',
                    'clients.show',
                ],
                true
            ) =>
                'clients.view',

            in_array(
                $routeName,
                [
                    'clients.create',
                    'clients.store',
                ],
                true
            ) =>
                'clients.create',

            in_array(
                $routeName,
                [
                    'clients.edit',
                    'clients.update',
                ],
                true
            ) =>
                'clients.edit',

            $routeName === 'clients.renew' =>
                'clients.renew',

            in_array(
                $routeName,
                [
                    'clients.suspend',
                    'clients.unsuspend',
                ],
                true
            ) =>
                'clients.suspend',

            $routeName === 'clients.destroy' =>
                'clients.delete',

            in_array(
                $routeName,
                [
                    'clients.invoices.print',
                    'clients.invoices.download',
                ],
                true
            ) =>
                'invoices.export',

            in_array(
                $routeName,
                [
                    'routers.index',
                    'routers.show',
                ],
                true
            ) =>
                'routers.view',

            str_starts_with(
                $routeName,
                'routers.'
            ) =>
                'routers.manage',

            in_array(
                $routeName,
                [
                    'packages.index',
                    'packages.show',
                ],
                true
            ) =>
                'packages.view',

            str_starts_with(
                $routeName,
                'packages.'
            ) =>
                'packages.manage',

            in_array(
                $routeName,
                [
                    'ip-ranges.index',
                    'ip-ranges.show',
                ],
                true
            ) =>
                'ip_pools.view',

            str_starts_with(
                $routeName,
                'ip-ranges.'
            ) =>
                'ip_pools.manage',

            $routeName === 'invoices.index' =>
                'invoices.view',

            str_contains(
                $routeName,
                'print'
            )
            && str_starts_with(
                $routeName,
                'invoices.'
            ) =>
                'invoices.export',

            str_contains(
                $routeName,
                'download'
            )
            && str_starts_with(
                $routeName,
                'invoices.'
            ) =>
                'invoices.export',

            str_starts_with(
                $routeName,
                'invoices.'
            ) =>
                'invoices.manage',

            $routeName === 'payments.index' =>
                'payments.view',

            in_array(
                $routeName,
                [
                    'payments.create',
                    'payments.store',
                ],
                true
            ) =>
                'payments.manage',

            $routeName === 'payments.destroy' =>
                'payments.delete',

            $routeName === 'expenses.index' =>
                'expenses.view',

            $routeName === 'expenses.destroy' =>
                'expenses.delete',

            str_starts_with(
                $routeName,
                'expenses.'
            ) =>
                'expenses.manage',

            $routeName === 'accounting.index' =>
                'accounting.view',

            in_array(
                $routeName,
                [
                    'accounting.print',
                    'accounting.download',
                ],
                true
            ) =>
                'accounting.export',

            str_starts_with(
                $routeName,
                'settings.'
            ) =>
                'settings.manage',

            default => null,
        };
    }
}
