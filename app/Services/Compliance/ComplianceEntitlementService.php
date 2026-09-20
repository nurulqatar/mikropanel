<?php

namespace App\Services\Compliance;

use App\Models\Compliance\Organization;
use App\Models\Compliance\Subscription;

class ComplianceEntitlementService
{
    public function logging(Organization|int $organization): bool
    {
        return $this->has($organization, 'logging_enabled');
    }

    public function filtering(Organization|int $organization): bool
    {
        return $this->has($organization, 'filtering_enabled');
    }

    private function has(Organization|int $organization, string $column): bool
    {
        $organizationId = $organization instanceof Organization
            ? $organization->id
            : $organization;

        return Subscription::query()
            ->where('organization_id', $organizationId)
            ->where($column, true)
            ->whereIn('status', ['active', 'trial'])
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
