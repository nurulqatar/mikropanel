<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\IpRange;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Router;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $today = today();
        $tomorrow = $today->copy()->addDay();
        $threeDaysLater = $today->copy()->addDays(3);

        $monthStart = $today
            ->copy()
            ->startOfMonth();

        $monthEnd = $today
            ->copy()
            ->endOfMonth();

        $totalClients = Client::query()->count();

        $activeClients = Client::query()
            ->where('enabled', true)
            ->count();

        $suspendedClients = Client::query()
            ->where('enabled', false)
            ->count();

        $onlineClients = Client::query()
            ->where('enabled', true)
            ->where('connected', true)
            ->count();

        $offlineClients = Client::query()
            ->where('enabled', true)
            ->where('connected', false)
            ->count();

        $expiredClients = Client::query()
            ->whereNotNull('expiry_date')
            ->whereDate(
                'expiry_date',
                '<',
                $today->toDateString()
            )
            ->count();

        $expireToday = Client::query()
            ->where('enabled', true)
            ->whereDate(
                'expiry_date',
                $today->toDateString()
            )
            ->count();

        $expireTomorrow = Client::query()
            ->where('enabled', true)
            ->whereDate(
                'expiry_date',
                $tomorrow->toDateString()
            )
            ->count();

        $expiringSoonCount = Client::query()
            ->where('enabled', true)
            ->whereBetween('expiry_date', [
                $today->toDateString(),
                $threeDaysLater->toDateString(),
            ])
            ->count();

        $monthlyCollection = (float) Payment::query()
            ->whereBetween('payment_date', [
                $monthStart->toDateString(),
                $monthEnd->toDateString(),
            ])
            ->sum('amount');

        $todayCollection = (float) Payment::query()
            ->whereDate(
                'payment_date',
                $today->toDateString()
            )
            ->sum('amount');

        $monthlyExpenses = $this->expenseSum(
            $monthStart->toDateString(),
            $monthEnd->toDateString()
        );

        $todayExpenses = $this->expenseSum(
            $today->toDateString(),
            $today->toDateString()
        );

        $totalDue = (float) Invoice::query()
            ->where('status', '!=', 'cancelled')
            ->sum('due_amount');

        $unpaidInvoices = Invoice::query()
            ->where('status', 'unpaid')
            ->where('due_amount', '>', 0)
            ->count();

        $partialInvoices = Invoice::query()
            ->where('status', 'partial')
            ->where('due_amount', '>', 0)
            ->count();

        $overdueQuery = Invoice::query()
            ->where('status', '!=', 'cancelled')
            ->where('due_amount', '>', 0)
            ->whereDate(
                'due_date',
                '<',
                $today->toDateString()
            );

        $overdueInvoiceCount = (
            clone $overdueQuery
        )->count();

        $overdueAmount = (float) (
            clone $overdueQuery
        )->sum('due_amount');

        $ipPools = $this->ipPoolStatistics();

        $nearFullPoolCount = collect($ipPools)
            ->where('near_full', true)
            ->count();

        $routerHealth = $this->routerStatistics();

        $offlineRouterCount = collect($routerHealth)
            ->where('connected', false)
            ->count();

        $expiringClientsList = Client::query()
            ->with([
                'package:id,name',
                'router:id,name',
            ])
            ->where('enabled', true)
            ->whereBetween('expiry_date', [
                $today->toDateString(),
                $threeDaysLater->toDateString(),
            ])
            ->orderBy('expiry_date')
            ->limit(8)
            ->get()
            ->map(function (Client $client): array {
                return [
                    'id' => $client->id,
                    'client_code' =>
                        $client->client_code,

                    'name' => $client->name,
                    'phone' => $client->phone,
                    'expiry_date' =>
                        $client->expiry_date
                            ?->format('Y-m-d'),

                    'package' =>
                        $client->package?->name,

                    'router' =>
                        $client->router?->name,

                    'connected' =>
                        (bool) $client->connected,
                ];
            });

        $overdueInvoicesList = Invoice::query()
            ->with([
                'client:id,name,client_code,phone',
            ])
            ->where('status', '!=', 'cancelled')
            ->where('due_amount', '>', 0)
            ->whereDate(
                'due_date',
                '<',
                $today->toDateString()
            )
            ->orderBy('due_date')
            ->limit(8)
            ->get()
            ->map(function (Invoice $invoice): array {
                return [
                    'id' => $invoice->id,
                    'invoice_no' =>
                        $invoice->invoice_no,

                    'due_date' =>
                        $this->dateValue(
                            $invoice->due_date
                        ),

                    'due_amount' =>
                        (float) $invoice->due_amount,

                    'status' => $invoice->status,
                    'client' => $invoice->client,
                ];
            });

        $recentClients = Client::query()
            ->with([
                'router:id,name',
                'package:id,name',
            ])
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (Client $client): array {
                return [
                    'id' => $client->id,
                    'client_code' =>
                        $client->client_code,

                    'name' => $client->name,
                    'ip_address' =>
                        $client->ip_address,

                    'enabled' =>
                        (bool) $client->enabled,

                    'connected' =>
                        (bool) $client->connected,

                    'expiry_date' =>
                        $client->expiry_date
                            ?->format('Y-m-d'),

                    'router' =>
                        $client->router?->name,

                    'package' =>
                        $client->package?->name,
                ];
            });

        $recentPayments = Payment::query()
            ->with([
                'client:id,name,client_code',
                'invoice:id,invoice_no',
            ])
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(function (Payment $payment): array {
                return [
                    'id' => $payment->id,
                    'amount' =>
                        (float) $payment->amount,

                    'payment_date' =>
                        $this->dateValue(
                            $payment->payment_date
                        ),

                    'payment_method' =>
                        $payment->payment_method,

                    'client' => $payment->client,
                    'invoice' => $payment->invoice,
                ];
            });

        return Inertia::render('Dashboard', [
            'quickDesk' => $this->quickDeskData(),

            'clientStats' => [
                'total' => $totalClients,
                'active' => $activeClients,
                'suspended' => $suspendedClients,
                'online' => $onlineClients,
                'offline' => $offlineClients,
                'expired' => $expiredClients,
            ],

            'systemStats' => [
                'routers' =>
                    Router::query()->count(),

                'packages' =>
                    Package::query()->count(),

                'ip_pools' =>
                    IpRange::query()->count(),
            ],

            'financialStats' => [
                'monthly_collection' =>
                    $monthlyCollection,

                'today_collection' =>
                    $todayCollection,

                'monthly_expenses' =>
                    $monthlyExpenses,

                'today_expenses' =>
                    $todayExpenses,

                'net_profit' =>
                    $monthlyCollection
                    - $monthlyExpenses,

                'total_due' => $totalDue,
                'overdue_amount' => $overdueAmount,
            ],

            'invoiceStats' => [
                'unpaid' => $unpaidInvoices,
                'partial' => $partialInvoices,
                'overdue' => $overdueInvoiceCount,
            ],

            'expiryStats' => [
                'today' => $expireToday,
                'tomorrow' => $expireTomorrow,
                'within_three_days' =>
                    $expiringSoonCount,

                'expired' => $expiredClients,
            ],

            'attention' => [
                'offline_clients' =>
                    $offlineClients,

                'offline_routers' =>
                    $offlineRouterCount,

                'expiring_soon' =>
                    $expiringSoonCount,

                'overdue_invoices' =>
                    $overdueInvoiceCount,

                'near_full_pools' =>
                    $nearFullPoolCount,
            ],

            'routerHealth' => $routerHealth,
            'ipPools' => $ipPools,

            'chartData' =>
                $this->sevenDayChart(),

            'expiringClients' =>
                $expiringClientsList,

            'overdueInvoices' =>
                $overdueInvoicesList,

            'recentClients' => $recentClients,
            'recentPayments' => $recentPayments,
        ]);
    }

    private function quickDeskData(): array
    {
        $user = request()->user();

        $permissions = [
            'view' => $this->can(
                $user,
                'clients.view'
            ),

            'create' => $this->can(
                $user,
                'clients.create'
            ),

            'edit' => $this->can(
                $user,
                'clients.edit'
            ),

            'renew' => $this->can(
                $user,
                'clients.renew'
            ),

            'suspend' => $this->can(
                $user,
                'clients.suspend'
            ),

            'invoice_export' => $this->can(
                $user,
                'invoices.export'
            ),
        ];

        if (!$permissions['view']) {
            return [
                'enabled' => false,
                'permissions' => $permissions,
                'clients' => [],
                'routers' => [],
                'packages' => [],
                'ip_ranges' => [],
            ];
        }

        $clients = Client::query()
            ->select([
                'id',
                'client_code',
                'router_id',
                'ip_range_id',
                'package_id',
                'name',
                'mac_address',
                'ip_address',
                'phone',
                'email',
                'address',
                'expiry_date',
                'installed_at',
                'billing_day',
                'enabled',
                'connected',
            ])
            ->with([
                'router:id,name',

                'package:id,name,price,validity_days,speed_download,speed_upload',

                'ipRange:id,router_id,name',
            ])
            ->withSum(
                [
                    'invoices as total_due' =>
                        function ($query): void {
                            $query->where(
                                'status',
                                '!=',
                                'cancelled'
                            );
                        },
                ],
                'due_amount'
            )
            ->orderByDesc('id')
            ->get()
            ->map(function (
                Client $client
            ): array {
                return [
                    'id' => $client->id,
                    'client_code' =>
                        $client->client_code,

                    'router_id' =>
                        $client->router_id,

                    'ip_range_id' =>
                        $client->ip_range_id,

                    'package_id' =>
                        $client->package_id,

                    'name' => $client->name,
                    'phone' => $client->phone,
                    'email' => $client->email,
                    'address' => $client->address,
                    'mac_address' =>
                        $client->mac_address,

                    'ip_address' =>
                        $client->ip_address,

                    'expiry_date' =>
                        $this->dateValue(
                            $client->expiry_date
                        ),

                    'installed_at' =>
                        $this->dateValue(
                            $client->installed_at
                        ),

                    'billing_day' =>
                        $client->billing_day,

                    'enabled' =>
                        (bool) $client->enabled,

                    'connected' =>
                        (bool) $client->connected,

                    'total_due' => round(
                        (float) (
                            $client->total_due
                            ?? 0
                        ),
                        2
                    ),

                    'router' => $client->router
                        ? [
                            'id' =>
                                $client->router->id,

                            'name' =>
                                $client->router->name,
                        ]
                        : null,

                    'package' => $client->package
                        ? [
                            'id' =>
                                $client->package->id,

                            'name' =>
                                $client->package->name,

                            'price' => (float)
                                $client->package->price,

                            'validity_days' => (int)
                                $client->package
                                    ->validity_days,

                            'speed_download' =>
                                $client->package
                                    ->speed_download,

                            'speed_upload' =>
                                $client->package
                                    ->speed_upload,
                        ]
                        : null,

                    'ip_range' => $client->ipRange
                        ? [
                            'id' =>
                                $client->ipRange->id,

                            'router_id' =>
                                $client->ipRange
                                    ->router_id,

                            'name' =>
                                $client->ipRange->name,
                        ]
                        : null,
                ];
            })
            ->values()
            ->all();

        return [
            'enabled' => true,
            'permissions' => $permissions,
            'clients' => $clients,

            'routers' => $permissions['edit']
                ? Router::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'enabled',
                    ])
                : [],

            'packages' => $permissions['edit']
                ? Package::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'price',
                        'validity_days',
                        'enabled',
                    ])
                : [],

            'ip_ranges' => $permissions['edit']
                ? IpRange::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'router_id',
                        'name',
                        'enabled',
                    ])
                : [],
        ];
    }

    private function can(
        mixed $user,
        string $permission
    ): bool {
        if (!$user || !$user->is_active) {
            return false;
        }

        return $user->isAdmin()
            || $user->hasPermission(
                $permission
            );
    }

    private function expenseSum(
        string $startDate,
        string $endDate
    ): float {
        if (
            !Schema::hasTable('expenses')
            || !Schema::hasColumn(
                'expenses',
                'expense_date'
            )
            || !Schema::hasColumn(
                'expenses',
                'amount'
            )
        ) {
            return 0;
        }

        return (float) Expense::query()
            ->whereBetween('expense_date', [
                $startDate,
                $endDate,
            ])
            ->sum('amount');
    }

    private function sevenDayChart(): array
    {
        $data = [];

        for ($daysAgo = 6; $daysAgo >= 0; $daysAgo--) {
            $date = today()->subDays($daysAgo);
            $dateString = $date->toDateString();

            $collection = (float) Payment::query()
                ->whereDate(
                    'payment_date',
                    $dateString
                )
                ->sum('amount');

            $expense = $this->expenseSum(
                $dateString,
                $dateString
            );

            $data[] = [
                'date' => $dateString,
                'label' => $date->format('D'),
                'collection' => $collection,
                'expense' => $expense,
                'profit' =>
                    $collection - $expense,
            ];
        }

        return $data;
    }

    private function ipPoolStatistics(): array
    {
        $usedCounts = Client::query()
            ->whereNotNull('ip_range_id')
            ->selectRaw(
                'ip_range_id, COUNT(*) as total'
            )
            ->groupBy('ip_range_id')
            ->pluck('total', 'ip_range_id');

        return IpRange::query()
            ->with('router:id,name')
            ->orderBy('name')
            ->get()
            ->map(function (
                IpRange $range
            ) use ($usedCounts): array {
                $capacity = $this->ipCapacity(
                    $range->start_ip,
                    $range->end_ip
                );

                $used = (int) (
                    $usedCounts[$range->id] ?? 0
                );

                $free = max(
                    0,
                    $capacity - $used
                );

                $percentage = $capacity > 0
                    ? round(
                        ($used / $capacity) * 100,
                        1
                    )
                    : 0;

                return [
                    'id' => $range->id,
                    'name' => $range->name,
                    'router' =>
                        $range->router?->name,

                    'network' => $range->network,
                    'start_ip' => $range->start_ip,
                    'end_ip' => $range->end_ip,
                    'enabled' =>
                        (bool) $range->enabled,

                    'capacity' => $capacity,
                    'used' => $used,
                    'free' => $free,
                    'percentage' => $percentage,

                    'near_full' =>
                        $capacity > 0
                        && (
                            $percentage >= 85
                            || $free <= 5
                        ),
                ];
            })
            ->values()
            ->all();
    }

    private function routerStatistics(): array
    {
        return Router::query()
            ->latest()
            ->get()
            ->map(function (
                Router $router
            ): array {
                $totalMemory = (float) (
                    $this->routerAttribute(
                        $router,
                        [
                            'total_memory',
                            'memory_total',
                        ],
                        0
                    )
                );

                $freeMemory = (float) (
                    $this->routerAttribute(
                        $router,
                        [
                            'free_memory',
                            'memory_free',
                        ],
                        0
                    )
                );

                $memoryPercentage = null;

                if ($totalMemory > 0) {
                    $memoryPercentage = round(
                        (
                            (
                                $totalMemory
                                - $freeMemory
                            )
                            / $totalMemory
                        ) * 100,
                        1
                    );
                }

                $lastChecked = $this->routerAttribute(
                    $router,
                    [
                        'last_checked_at',
                        'last_seen_at',
                    ]
                );

                return [
                    'id' => $router->id,
                    'name' => $router->name,
                    'host' => $router->host,
                    'enabled' =>
                        (bool) $router->enabled,

                    'connected' =>
                        (bool) $router->connected,

                    'identity' =>
                        $this->routerAttribute(
                            $router,
                            ['identity']
                        ),

                    'version' =>
                        $this->routerAttribute(
                            $router,
                            [
                                'routeros_version',
                                'version',
                            ]
                        ),

                    'board_name' =>
                        $this->routerAttribute(
                            $router,
                            ['board_name']
                        ),

                    'uptime' =>
                        $this->routerAttribute(
                            $router,
                            ['uptime']
                        ),

                    'cpu_load' =>
                        $this->routerAttribute(
                            $router,
                            ['cpu_load']
                        ),

                    'memory_percentage' =>
                        $memoryPercentage,

                    'dhcp_count' =>
                        $this->routerAttribute(
                            $router,
                            [
                                'dhcp_leases_count',
                                'dhcp_lease_count',
                            ],
                            0
                        ),

                    'arp_count' =>
                        $this->routerAttribute(
                            $router,
                            [
                                'arp_entries_count',
                                'arp_count',
                            ],
                            0
                        ),

                    'queue_count' =>
                        $this->routerAttribute(
                            $router,
                            [
                                'simple_queues_count',
                                'queue_count',
                            ],
                            0
                        ),

                    'last_checked_at' =>
                        $lastChecked
                            ? Carbon::parse(
                                $lastChecked
                            )->format(
                                'Y-m-d H:i:s'
                            )
                            : null,

                    'last_error' =>
                        $this->routerAttribute(
                            $router,
                            ['last_error']
                        ),
                ];
            })
            ->values()
            ->all();
    }

    private function routerAttribute(
        Router $router,
        array $names,
        mixed $default = null
    ): mixed {
        foreach ($names as $name) {
            if (
                array_key_exists(
                    $name,
                    $router->getAttributes()
                )
                && $router->getAttribute($name)
                    !== null
            ) {
                return $router->getAttribute(
                    $name
                );
            }
        }

        return $default;
    }

    private function ipCapacity(
        ?string $startIp,
        ?string $endIp
    ): int {
        if (!$startIp || !$endIp) {
            return 0;
        }

        $start = ip2long($startIp);
        $end = ip2long($endIp);

        if ($start === false || $end === false) {
            return 0;
        }

        $startUnsigned = (int) sprintf(
            '%u',
            $start
        );

        $endUnsigned = (int) sprintf(
            '%u',
            $end
        );

        if ($endUnsigned < $startUnsigned) {
            return 0;
        }

        return $endUnsigned
            - $startUnsigned
            + 1;
    }

    private function dateValue(
        mixed $value
    ): ?string {
        return $value
            ? Carbon::parse($value)
                ->format('Y-m-d')
            : null;
    }
}
