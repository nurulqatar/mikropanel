<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPanelPermission
{
    /**
     * Protect every operator action using
     * the permission assigned by Admin.
     *
     * Admin users bypass permission checks.
     */
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        /*
         * Routes using auth middleware will normally
         * never reach here without a user.
         */
        if (!$user) {
            return $next($request);
        }

        /*
         * Defense in depth.
         * active.panel.user should already enforce this.
         */
        if (!$user->is_active) {
            abort(
                403,
                'Your panel account is inactive.'
            );
        }

        /*
         * Administrator always has complete access.
         */
        if ($user->isAdmin()) {
            return $next($request);
        }

        $routeName =
            $request->route()?->getName();

        $required =
            $this->permissionRequirementForRoute(
                $routeName
            );

        /*
         * Explicitly allowed route.
         * Currently used for own profile routes.
         */
        if ($required === null) {
            return $next($request);
        }

        /*
         * User Management is intentionally Admin-only.
         * Operators must never create or promote users.
         */
        if ($required === '__admin__') {
            abort(
                403,
                'Only administrators can manage panel users.'
            );
        }

        /*
         * Fail closed for any panel route that has not
         * yet been assigned a permission.
         */
        if ($required === '__deny__') {
            abort(
                403,
                'This panel action has not been assigned to your operator permissions.'
            );
        }

        /*
         * A route may accept ANY permission in an array.
         *
         * Example:
         * client-custom-fields.data is needed from
         * Add Client, Edit Client and Client Details.
         */
        $permissions =
            is_array($required)
                ? $required
                : [$required];

        $allowed = false;

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            abort(
                403,
                'You do not have permission to perform this action.'
            );
        }

        return $next($request);
    }

    /**
     * Central permission map for the complete panel.
     *
     * @return string|array<int,string>|null
     */
    private function permissionRequirementForRoute(
        ?string $routeName
    ): string|array|null {
        if (!$routeName) {
            return '__deny__';
        }

        return match (true) {

            /*
             * =================================================
             * OWN PROFILE
             * =================================================
             *
             * Any active authenticated user may manage
             * their own profile.
             */
            str_starts_with(
                $routeName,
                'profile.'
            ) => null,

            /*
             * =================================================
             * ADMIN-ONLY USER MANAGEMENT
             * =================================================
             */
            str_starts_with(
                $routeName,
                'users.'
            ) => '__admin__',

            /*
             * =================================================
             * DASHBOARD + QUICK DESK
             * =================================================
             */
            $routeName === 'dashboard',
            str_starts_with(
                $routeName,
                'dashboard.'
            ) =>
                'dashboard.view',

            /*
             * =================================================
             * DYNAMIC CLIENT CUSTOM FIELD READ ENDPOINTS
             * =================================================
             */

            /*
             * Add Client can have only clients.create.
             * Edit Client can have only clients.edit.
             * Client Details can have only clients.view.
             *
             * Therefore ANY of these permissions is valid.
             */
            $routeName ===
                'client-custom-fields.data' =>
                [
                    'clients.view',
                    'clients.create',
                    'clients.edit',
                ],

            /*
             * Client list returns values for multiple clients.
             */
            $routeName ===
                'client-custom-fields.list-data' =>
                'clients.view',

            /*
             * Future custom-field read route:
             * same safe client permission group.
             */
            str_starts_with(
                $routeName,
                'client-custom-fields.'
            ) =>
                [
                    'clients.view',
                    'clients.create',
                    'clients.edit',
                ],

            /*
             * =================================================
             * CLIENTS
             * =================================================
             */

            in_array(
                $routeName,
                [
                    'clients.index',
                    'clients.show',
                ],
                true
            ) =>
                'clients.view',

            /*
             * Archived/list/search style client pages.
             */
            str_starts_with(
                $routeName,
                'clients.archived'
            ) =>
                'clients.view',

            str_contains(
                $routeName,
                'search'
            )
            && str_starts_with(
                $routeName,
                'clients.'
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

            /*
             * Restoring archived client is treated
             * as Client Edit/Management.
             */
            str_contains(
                $routeName,
                'restore'
            )
            && str_starts_with(
                $routeName,
                'clients.'
            ) =>
                'clients.edit',

            /*
             * Client billing / renew / Quick Pay.
             */
            $routeName === 'clients.renew',
            (
                str_starts_with(
                    $routeName,
                    'clients.'
                )
                && (
                    str_contains(
                        $routeName,
                        'renew'
                    )
                    || str_contains(
                        $routeName,
                        'pay-due'
                    )
                )
            ) =>
                'clients.renew',

            /*
             * Suspend / Activate lifecycle.
             */
            in_array(
                $routeName,
                [
                    'clients.suspend',
                    'clients.unsuspend',
                ],
                true
            ) =>
                'clients.suspend',

            (
                str_starts_with(
                    $routeName,
                    'clients.'
                )
                && (
                    str_contains(
                        $routeName,
                        'activate'
                    )
                    || str_contains(
                        $routeName,
                        'deactivate'
                    )
                    || str_contains(
                        $routeName,
                        'suspend'
                    )
                )
            ) =>
                'clients.suspend',

            /*
             * Client archive/permanent-delete style actions.
             */
            $routeName === 'clients.destroy' =>
                'clients.delete',

            (
                str_starts_with(
                    $routeName,
                    'clients.'
                )
                && (
                    str_contains(
                        $routeName,
                        'force-delete'
                    )
                    || str_contains(
                        $routeName,
                        'purge'
                    )
                    || str_contains(
                        $routeName,
                        'archive'
                    )
                )
            ) =>
                'clients.delete',

            /*
             * Invoice Print/PDF from Client Details.
             */
            in_array(
                $routeName,
                [
                    'clients.invoices.print',
                    'clients.invoices.download',
                ],
                true
            ) =>
                'invoices.export',

            str_starts_with(
                $routeName,
                'clients.invoices.'
            ) =>
                'invoices.export',

            /*
             * Unknown future Client action is denied
             * until explicitly classified.
             */
            str_starts_with(
                $routeName,
                'clients.'
            ) =>
                '__deny__',

            /*
             * =================================================
             * ROUTERS
             * =================================================
             */

            in_array(
                $routeName,
                [
                    'routers.index',
                    'routers.show',
                ],
                true
            ) =>
                'routers.view',

            /*
             * Create/Edit/Ping/Sync/Delete
             */
            str_starts_with(
                $routeName,
                'routers.'
            ) =>
                'routers.manage',

            /*
             * =================================================
             * PACKAGES
             * =================================================
             */

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

            /*
             * =================================================
             * GLOBAL IP POOLS
             * =================================================
             */

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

            /*
             * =================================================
             * INVOICES
             * =================================================
             */

            $routeName === 'invoices.index' =>
                'invoices.view',

            (
                str_starts_with(
                    $routeName,
                    'invoices.'
                )
                && (
                    str_contains(
                        $routeName,
                        'print'
                    )
                    || str_contains(
                        $routeName,
                        'download'
                    )
                    || str_contains(
                        $routeName,
                        'export'
                    )
                )
            ) =>
                'invoices.export',

            /*
             * Create/Edit/Update/Delete
             */
            str_starts_with(
                $routeName,
                'invoices.'
            ) =>
                'invoices.manage',

            /*
             * =================================================
             * PAYMENTS
             * =================================================
             */

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

            str_starts_with(
                $routeName,
                'payments.'
            ) =>
                'payments.manage',

            /*
             * =================================================
             * EXPENSES
             * =================================================
             */

            in_array(
                $routeName,
                [
                    'expenses.index',
                    'expenses.show',
                ],
                true
            ) =>
                'expenses.view',

            $routeName === 'expenses.destroy' =>
                'expenses.delete',

            str_starts_with(
                $routeName,
                'expenses.'
            ) =>
                'expenses.manage',

            /*
             * =================================================
             * ACCOUNTING + CLIENT REPORTS
             * =================================================
             */

            $routeName === 'accounting.index' =>
                'accounting.view',

            (
                str_starts_with(
                    $routeName,
                    'accounting.'
                )
                && (
                    str_contains(
                        $routeName,
                        'print'
                    )
                    || str_contains(
                        $routeName,
                        'download'
                    )
                    || str_contains(
                        $routeName,
                        'export'
                    )
                )
            ) =>
                'accounting.export',

            /*
             * Future accounting read/filter endpoints.
             */
            str_starts_with(
                $routeName,
                'accounting.'
            ) =>
                'accounting.view',

            /*
             * =================================================
             * NOTIFICATION INBOX
             * =================================================
             *
             * Notification configuration inside Settings
             * remains settings.manage.
             */

            in_array(
                $routeName,
                [
                    'notifications.index',
                    'notifications.show',
                    'notification.index',
                    'notification.show',
                ],
                true
            ) =>
                'notifications.view',

            (
                (
                    str_starts_with(
                        $routeName,
                        'notifications.'
                    )
                    || str_starts_with(
                        $routeName,
                        'notification.'
                    )
                )
                && (
                    str_contains(
                        $routeName,
                        'count'
                    )
                    || str_contains(
                        $routeName,
                        'unread'
                    )
                    || str_contains(
                        $routeName,
                        'feed'
                    )
                )
            ) =>
                'notifications.view',

            /*
             * Mark read / mark all / retry / delete etc.
             */
            str_starts_with(
                $routeName,
                'notifications.'
            ),
            str_starts_with(
                $routeName,
                'notification.'
            ) =>
                'notifications.manage',

            /*
             * =================================================
             * SETTINGS + CLIENT FORM BUILDER
             * =================================================
             *
             * Client Form Builder is intentionally included
             * in Manage Panel Settings.
             */
            str_starts_with(
                $routeName,
                'settings.'
            ) =>
                'settings.manage',

            /*
             * =================================================
             * FAIL CLOSED
             * =================================================
             */
            in_array(
                $routeName,
                [
                    'hotspot.index',
                    'hotspot.servers.index',
                    'hotspot.plans.index',
                    'hotspot.vouchers.index',
                    'hotspot.vouchers.show',
                    'hotspot.batches.index',
                    'hotspot.sessions.index',
                    'hotspot.billing.index',
                    'hotspot.reports.index',
                ],
                true
            ) =>
                'hotspot.view',

            $routeName ===
                'hotspot.vouchers.sell' =>
                'hotspot.sell',

            $routeName ===
                'hotspot.invoices.pay' =>
                'hotspot.payments',

            in_array(
                $routeName,
                [
                    'hotspot.vouchers.print',
                    'hotspot.vouchers.pdf',
                    'hotspot.batches.print',
                    'hotspot.batches.pdf',
                    'hotspot.reports.csv',
                    'hotspot.reports.pdf',
                ],
                true
            ) =>
                'hotspot.export',

            str_starts_with(
                $routeName,
                'hotspot.'
            ) =>
                'hotspot.manage',

            default =>
                '__deny__',
        };
    }
}
