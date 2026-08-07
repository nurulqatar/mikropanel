<?php

namespace App\Http\Controllers;

use App\Exceptions\ClientProvisioningException;
use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\ClientMonthlyUsage;
use App\Models\IpRange;
use App\Models\Package;
use App\Models\Router;
use App\Services\ClientProvisionService;
use App\Services\ClientUsageService;
use App\Services\IpAllocatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ClientController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Clients/Index', [
            'clients' => Client::query()
                ->with([
                    'router',
                    'package',
                    'ipRange',
                ])
                ->withSum(
                    [
                        'invoices as total_due' =>
                            function ($query) {
                                $query->where(
                                    'status',
                                    '!=',
                                    'cancelled'
                                );
                            },
                    ],
                    'due_amount'
                )
                ->latest()
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Clients/Create', [
            'routers' => Router::query()
                ->where('enabled', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]),

            'packages' => Package::query()
                ->where('enabled', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'price',
                    'validity_days',
                    'speed_download',
                    'speed_upload',
                ]),

            'ipRanges' => IpRange::query()
                ->where('enabled', true)
                ->orderBy('name')
                ->get([
                    'id',
                    'router_id',
                    'name',
                    'network',
                    'gateway',
                    'start_ip',
                    'end_ip',
                ]),
        ]);
    }

    public function store(
        ClientRequest $request,
        IpAllocatorService $allocator,
        ClientProvisionService $provision
    ): RedirectResponse {
        $data = $request->validated();

        /*
         * IP Pool is global.
         * It is NOT tied to a selected router.
         */
        $range = IpRange::query()
            ->where(
                'id',
                $data['ip_range_id']
            )
            ->where(
                'enabled',
                true
            )
            ->first();

        if (!$range) {
            return back()
                ->withErrors([
                    'ip_range_id' =>
                        'Selected IP Pool is not available.',
                ])
                ->withInput();
        }

        $ip = $allocator->allocate(
            $range
        );

        if (!$ip) {
            return back()
                ->withErrors([
                    'ip_range_id' =>
                        'No free IP is available in this IP Pool.',
                ])
                ->withInput();
        }

        $package = Package::query()
            ->where(
                'id',
                $data['package_id']
            )
            ->where(
                'enabled',
                true
            )
            ->first();

        if (!$package) {
            return back()
                ->withErrors([
                    'package_id' =>
                        'Selected Package is not available.',
                ])
                ->withInput();
        }

        $validityDays =
            (int) $package->validity_days;

        if ($validityDays < 1) {
            return back()
                ->withErrors([
                    'package_id' =>
                        'Package validity days are missing.',
                ])
                ->withInput();
        }

        /*
         * clients.router_id remains an internal
         * legacy primary-router reference only.
         *
         * Operator no longer selects it.
         */
        $primaryRouter = Router::query()
            ->where(
                'enabled',
                true
            )
            ->orderBy('id')
            ->first();

        if (!$primaryRouter) {
            return back()
                ->withErrors([
                    'ip_range_id' =>
                        'At least one enabled MikroTik router is required.',
                ])
                ->withInput();
        }

        $startDate = today();

        $expiryDate =
            $validityDays === 30
                ? $startDate
                    ->copy()
                    ->addMonthNoOverflow()
                : $startDate
                    ->copy()
                    ->addDays(
                        $validityDays
                    );

        $data['router_id'] =
            $primaryRouter->id;

        $data['ip_range_id'] =
            $range->id;

        $data['ip_address'] =
            $ip;

        $data['mac_address'] =
            strtoupper(
                trim(
                    $data['mac_address']
                )
            );

        $data['installed_at'] =
            $startDate->toDateString();

        $data['expiry_date'] =
            $expiryDate->toDateString();

        $data['billing_day'] =
            $startDate->day;

        $data['enabled'] = true;
        $data['connected'] = false;

        $nextId =
            (
                Client::withTrashed()
                    ->max('id')
                ?? 0
            ) + 1;

        $data['client_code'] =
            'CLI-'
            . str_pad(
                (string) $nextId,
                5,
                '0',
                STR_PAD_LEFT
            );

        $client = null;

        try {
            $client = Client::create(
                $data
            );

            /*
             * Multi-router service synchronizes
             * this global client to every
             * enabled MikroTik.
             */
            $provision->provision(
                $client
            );

        } catch (
            ClientProvisioningException $exception
        ) {
            if ($client) {
                try {
                    if (
                        $exception
                            ->compensationComplete
                    ) {
                        $client->forceDelete();
                    } else {
                        $client->forceFill([
                            'enabled' => false,
                            'connected' => false,
                        ])->save();
                    }
                } catch (Throwable $cleanupError) {
                    Log::critical(
                        'Failed to clean up client after provisioning error.',
                        [
                            'client_id' =>
                                $client->id,

                            'message' =>
                                $cleanupError
                                    ->getMessage(),
                        ]
                    );
                }
            }

            Log::error(
                'Global client provisioning failed.',
                [
                    'client_id' =>
                        $client?->id,

                    'message' =>
                        $exception
                            ->getMessage(),

                    'compensation_complete' =>
                        $exception
                            ->compensationComplete,
                ]
            );

            return back()
                ->withErrors([
                    'mac_address' =>
                        'Client could not be synchronized to MikroTik. Please try again.',
                ])
                ->withInput();

        } catch (Throwable $exception) {
            if ($client) {
                try {
                    $client->forceFill([
                        'enabled' => false,
                        'connected' => false,
                    ])->save();
                } catch (Throwable) {
                    // Preserve original exception.
                }
            }

            Log::error(
                'Client creation failed.',
                [
                    'client_id' =>
                        $client?->id,

                    'message' =>
                        $exception
                            ->getMessage(),
                ]
            );

            return back()
                ->withErrors([
                    'mac_address' =>
                        'Client could not be created. Please try again.',
                ])
                ->withInput();
        }

        return redirect()
            ->route('clients.index')
            ->with(
                'success',
                'Client created successfully and multi-router synchronization started.'
            );
    }

    public function show(
        Client $client,
        ClientUsageService $usageService
    ): Response {
        /*
         * Details page খোলার সময়ও একটি live sync
         * চেষ্টা করা হবে।
         */
        try {
            $usageService->syncClient($client);
        } catch (Throwable $exception) {
            Log::warning(
                'Live client usage sync failed.',
                [
                    'client_id' => $client->id,
                    'message' =>
                        $exception->getMessage(),
                ]
            );
        }

        $client->load([
            'router',
            'package',
            'ipRange',

            'invoices' => function ($query) {
                $query
                    ->orderByDesc('issue_date')
                    ->orderByDesc('id');
            },

            'payments' => function ($query) {
                $query
                    ->with([
                        'invoice:id,invoice_no',
                        'receiver:id,name',
                    ])
                    ->orderByDesc('payment_date')
                    ->orderByDesc('id');
            },
        ]);

        $totalBilled = $client->invoices->sum(
            function ($invoice) {
                return (float) $invoice->amount
                    - (float) $invoice->discount;
            }
        );

        $usage = ClientMonthlyUsage::query()
            ->where('client_id', $client->id)
            ->where(
                'usage_month',
                today()
                    ->startOfMonth()
                    ->toDateString()
            )
            ->first();

        $uploadBytes = (int) (
            $usage?->upload_bytes ?? 0
        );

        $downloadBytes = (int) (
            $usage?->download_bytes ?? 0
        );

        return Inertia::render('Clients/Show', [
            'client' => $client,

            'billingSummary' => [
                'invoice_count' =>
                    $client->invoices->count(),

                'total_billed' =>
                    $totalBilled,

                'total_paid' =>
                    (float) $client
                        ->payments
                        ->sum('amount'),

                'total_due' =>
                    (float) $client
                        ->invoices
                        ->sum('due_amount'),
            ],

            'usageSummary' => [
                'month' => now()->format('F Y'),
                'upload_bytes' => $uploadBytes,
                'download_bytes' => $downloadBytes,
                'total_bytes' =>
                    $uploadBytes + $downloadBytes,

                'last_synced_at' =>
                    $usage?->last_synced_at
                        ?->format('Y-m-d H:i:s'),
            ],
        ]);
    }

    public function edit(Client $client): Response
    {
        return Inertia::render('Clients/Edit', [
            'client' => $client,

            'routers' => Router::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]),

            'packages' => Package::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'speed_download',
                    'speed_upload',
                    'mikrotik_profile',
                ]),

            'ipRanges' => IpRange::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'router_id',
                    'name',
                    'network',
                    'gateway',
                    'start_ip',
                    'end_ip',
                ]),
        ]);
    }

    public function update(
        ClientRequest $request,
        Client $client,
        ClientProvisionService $provision
    ): RedirectResponse {
        $data = $request->validated();

        unset(
            $data['router_id'],
            $data['enabled']
        );

        $data['mac_address'] =
            strtoupper(
                trim(
                    $data['mac_address']
                )
            );

        /*
         * IP Pool is global, but changing an
         * existing client's pool would also
         * require a controlled IP re-allocation.
         *
         * Keep the current pool/IP fixed here.
         */
        $range = IpRange::query()
            ->find(
                $data['ip_range_id']
            );

        if (!$range) {
            return back()
                ->withErrors([
                    'ip_range_id' =>
                        'Selected IP Pool does not exist.',
                ])
                ->withInput();
        }

        if (
            (int) $range->id
            !== (int) $client->ip_range_id
        ) {
            return back()
                ->withErrors([
                    'ip_range_id' =>
                        'IP Pool cannot be changed for an existing client.',
                ])
                ->withInput();
        }

        $package = Package::query()
            ->find(
                $data['package_id']
            );

        if (
            !$package
            || (
                !$package->enabled
                && (int) $package->id
                    !== (int) $client->package_id
            )
        ) {
            return back()
                ->withErrors([
                    'package_id' =>
                        'Selected Package is not available.',
                ])
                ->withInput();
        }

        $client->load([
            'package',
            'ipRange',
        ]);

        $before = clone $client;

        $before->setRelations(
            $client->getRelations()
        );

        $candidate = clone $client;

        $candidate->setRelations(
            $client->getRelations()
        );

        $candidate->fill(
            $data
        );

        $candidate->setRelation(
            'package',
            $package
        );

        $candidate->setRelation(
            'ipRange',
            $range
        );

        /*
         * MikroTik FIRST.
         * Multi-router service converges every
         * enabled router to the new client state.
         */
        try {
            $provision->update(
                $candidate,
                $before
            );

        } catch (
            ClientProvisioningException $exception
        ) {
            Log::error(
                'Global client update failed.',
                [
                    'client_id' =>
                        $client->id,

                    'message' =>
                        $exception
                            ->getMessage(),

                    'compensation_complete' =>
                        $exception
                            ->compensationComplete,
                ]
            );

            return back()
                ->withErrors([
                    'mac_address' =>
                        'MikroTik synchronization failed. Client was not saved.',
                ])
                ->withInput();
        }

        /*
         * Save DB only after MikroTik update.
         */
        try {
            $client->update(
                $data
            );

        } catch (Throwable $exception) {
            try {
                $provision->update(
                    $before
                );
            } catch (Throwable $rollbackError) {
                Log::critical(
                    'Database update failed and MikroTik rollback also failed.',
                    [
                        'client_id' =>
                            $client->id,

                        'db_error' =>
                            $exception
                                ->getMessage(),

                        'rollback_error' =>
                            $rollbackError
                                ->getMessage(),
                    ]
                );
            }

            Log::error(
                'Client database update failed.',
                [
                    'client_id' =>
                        $client->id,

                    'message' =>
                        $exception
                            ->getMessage(),
                ]
            );

            return back()
                ->withErrors([
                    'mac_address' =>
                        'Client update could not be saved.',
                ])
                ->withInput();
        }

        return redirect()
            ->route('clients.index')
            ->with(
                'success',
                'Client updated across enabled MikroTik routers.'
            );
    }

    public function suspend(
        Client $client,
        ClientProvisionService $provision
    ): RedirectResponse {
        try {
            $provision->suspend(
                $client
            );

        } catch (
            ClientProvisioningException
            $exception
        ) {
            Log::error(
                'Manual client suspension failed.',
                [
                    'client_id' =>
                        $client->id,

                    'compensation_complete' =>
                        $exception
                            ->compensationComplete,

                    'message' =>
                        $exception
                            ->getMessage(),
                ]
            );

            return back()
                ->withErrors([
                    'client' =>
                        $exception
                            ->compensationComplete
                            ? 'Suspension failed. Previous MikroTik state was restored automatically.'
                            : 'Suspension failed and MikroTik rollback was incomplete. Check this client before retrying.',
                ]);
        }

        return back()->with(
            'success',
            'Client suspended successfully.'
        );
    }

    public function unsuspend(
        Client $client,
        ClientProvisionService $provision
    ): RedirectResponse {
        try {
            $provision->unsuspend(
                $client
            );

        } catch (
            ClientProvisioningException
            $exception
        ) {
            Log::error(
                'Manual client activation failed.',
                [
                    'client_id' =>
                        $client->id,

                    'compensation_complete' =>
                        $exception
                            ->compensationComplete,

                    'message' =>
                        $exception
                            ->getMessage(),
                ]
            );

            return back()
                ->withErrors([
                    'client' =>
                        $exception
                            ->compensationComplete
                            ? 'Activation failed. Client was kept suspended on MikroTik.'
                            : 'Activation failed and MikroTik rollback was incomplete. Check this client before retrying.',
                ]);
        }

        return back()->with(
            'success',
            'Client activated successfully.'
        );
    }

    public function destroy(
        Client $client,
        ClientProvisionService $provision
    ): RedirectResponse {
        try {
            /*
             * MikroTik first:
             * Queue + ARP + DHCP Lease.
             */
            $provision->remove(
                $client
            );

        } catch (
            ClientProvisioningException
            $exception
        ) {
            Log::error(
                'Client archive blocked because MikroTik cleanup was incomplete.',
                [
                    'client_id' =>
                        $client->id,

                    'message' =>
                        $exception
                            ->getMessage(),
                ]
            );

            /*
             * Never hide/archive a panel record
             * while Router objects may remain.
             */
            return back()
                ->withErrors([
                    'client' =>
                        'Client was not archived because MikroTik cleanup was incomplete.',
                ]);
        }

        try {
            $client->forceFill([
                'enabled' => false,
                'connected' => false,
            ])->save();

            /*
             * Soft delete only.
             * Invoice/payment/accounting history
             * remains attached.
             */
            $client->delete();

        } catch (Throwable $exception) {
            Log::error(
                'Client DB archive failed after MikroTik removal.',
                [
                    'client_id' =>
                        $client->id,

                    'message' =>
                        $exception
                            ->getMessage(),
                ]
            );

            /*
             * Router objects were removed but
             * DB archive failed. Try restoring
             * normal MikroTik service.
             */
            try {
                $client->refresh();

                $client->load([
                    'router',
                    'package',
                    'ipRange',
                ]);

                $provision->provision(
                    $client
                );

                Log::warning(
                    'MikroTik service restored after DB archive failure.',
                    [
                        'client_id' =>
                            $client->id,
                    ]
                );

            } catch (Throwable $restoreError) {
                Log::critical(
                    'DB archive failed and MikroTik restore also failed.',
                    [
                        'client_id' =>
                            $client->id,

                        'archive_error' =>
                            $exception
                                ->getMessage(),

                        'restore_error' =>
                            $restoreError
                                ->getMessage(),
                    ]
                );

                return back()
                    ->withErrors([
                        'client' =>
                            'Archive failed and MikroTik restoration was incomplete. Check this client before continuing.',
                    ]);
            }

            return back()
                ->withErrors([
                    'client' =>
                        'Archive was not saved. MikroTik service was restored automatically.',
                ]);
        }

        return back()->with(
            'success',
            'Client archived successfully. Invoice, payment and accounting history have been preserved.'
        );
    }

}
