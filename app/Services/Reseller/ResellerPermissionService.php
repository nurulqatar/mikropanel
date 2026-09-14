<?php

namespace App\Services\Reseller;

class ResellerPermissionService
{
    public function ownerPermissions(): array
    {
        return array_keys(
            $this->options()
        );
    }

    public function operatorPermissions(): array
    {
        return array_keys(
            $this->options()
        );
    }

    public function options(): array
    {
        $config =
            config(
                'panel_permissions',
                []
            );

        $groups =
            $config['groups']
            ?? $config;

        $result = [];

        foreach ($groups as $group) {
            $permissions =
                $group['permissions']
                ?? [];

            foreach (
                $permissions
                as $key => $value
            ) {
                $permission = null;
                $label = null;

                if (is_string($key)) {
                    $permission = $key;

                    $label =
                        is_string($value)
                            ? $value
                            : (
                                $value['label']
                                ?? $key
                            );
                } elseif (
                    is_array($value)
                ) {
                    $permission =
                        $value['key']
                        ?? $value['permission']
                        ?? null;

                    $label =
                        $value['label']
                        ?? $permission;
                } elseif (
                    is_string($value)
                ) {
                    $permission =
                        $value;

                    $label =
                        $value;
                }

                if (
                    !$permission
                    || !$this->safe(
                        $permission
                    )
                ) {
                    continue;
                }

                $result[
                    $permission
                ] = $label;
            }
        }

        ksort($result);

        return $result;
    }

    private function safe(
        string $permission
    ): bool {
        foreach ([
            'dashboard.',
            'clients.',
            'routers.',
            'packages.',
            'ip_pools.',
            'invoices.',
            'payments.',
            'expenses.',
            'accounting.',
            'hotspot.',
        ] as $prefix) {
            if (
                str_starts_with(
                    $permission,
                    $prefix
                )
            ) {
                return true;
            }
        }

        return false;
    }
}
