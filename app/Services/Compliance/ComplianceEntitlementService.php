<?php

namespace App\Services\Compliance;

use App\Models\Compliance\Organization;
use App\Models\Compliance\Subscription;

class ComplianceEntitlementService
{
    public function logging(Organization|int $organization): bool
    {
        return $this->has($organization, 'logging_enabled', 'logging');
    }

    public function filtering(Organization|int $organization): bool
    {
        return $this->has($organization, 'filtering_enabled', 'filtering');
    }

    private function has(
        Organization|int $organization,
        string $column,
        string $maskKey
    ): bool {
        $organizationId = $organization instanceof Organization
            ? $organization->id
            : $organization;

        $active = Subscription::query()
            ->where('organization_id', $organizationId)
            ->where($column, true)
            ->whereIn('status', ['active', 'trial'])
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();

        if (!$active) {
            return false;
        }

        if (
            !app()->runningInConsole()
            && request()->hasSession()
            && (int) request()->session()->get('compliance_organization_id', 0)
                === (int) $organizationId
            && request()->session()->has('compliance_service_mask')
        ) {
            $mask = request()->session()->get('compliance_service_mask', []);
            return (bool) ($mask[$maskKey] ?? false);
        }

        return true;
    }
}
