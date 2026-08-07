<?php

namespace App\Services;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class ClientProvisionService
{
    public function __construct(
        protected DhcpLeaseService $dhcpLeaseService,
        protected ArpService $arpService,
        protected QueueService $queueService,
    ) {
    }

    public function provision(Client $client): void
    {
        DB::transaction(function () use ($client) {

            $leaseId = $this->dhcpLeaseService->create($client);

            $arpId = $this->arpService->create($client);

            $queueId = $this->queueService->create($client);

            $client->update([
                'mikrotik_lease_id' => $leaseId,
                'mikrotik_arp_id' => $arpId,
                'mikrotik_queue_id' => $queueId,
            ]);

        });
    }

    public function update(Client $client): void
    {
        DB::transaction(function () use ($client) {

            $this->dhcpLeaseService->update($client);

            $this->arpService->update($client);

            $this->queueService->update($client);

        });
    }
public function suspend(Client $client): void
{
    $this->queueService->disable($client);

    $this->arpService->disable($client);

    $this->dhcpLeaseService->disable($client);

    $client->update([
        'enabled' => false,
    ]);
}

public function unsuspend(Client $client): void
{
    $this->queueService->enable($client);

    $this->arpService->enable($client);

    $this->dhcpLeaseService->enable($client);

    $client->update([
        'enabled' => true,
    ]);
}
    public function remove(Client $client): void
    {
        $this->queueService->remove($client);

        $this->arpService->remove($client);

        $this->dhcpLeaseService->remove($client);
    }
}
