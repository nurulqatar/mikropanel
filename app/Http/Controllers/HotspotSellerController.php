<?php

namespace App\Http\Controllers;

use App\Models\HotspotBatch;
use App\Models\HotspotInvoice;
use App\Models\HotspotSeller;
use App\Models\HotspotSellerCollection;
use App\Models\HotspotVoucher;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class HotspotSellerController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->viewAccess(
            $request
        );

        $tenantId =
            $this->tenantId(
                $request
            );

        $sellers =
            $this->tenant(
                HotspotSeller::query(),
                $tenantId
            )
                ->orderBy('name')
                ->get()
                ->map(
                    function (
                        HotspotSeller $seller
                    ): array {
                        $stats =
                            $this->sellerStats(
                                $seller->id
                            );

                        return [
                            'id' =>
                                $seller->id,

                            'code' =>
                                $seller->code,

                            'name' =>
                                $seller->name,

                            'phone' =>
                                $seller->phone,

                            'notes' =>
                                $seller->notes,

                            'active' =>
                                (bool)
                                $seller->active,

                            ...$stats,
                        ];
                    }
                )
                ->values();

        $summary = [
            'sellers' =>
                $sellers->count(),

            'active_sellers' =>
                $sellers
                    ->where(
                        'active',
                        true
                    )
                    ->count(),

            'sold_vouchers' =>
                $sellers->sum(
                    'sold_count'
                ),

            'sales_amount' =>
                round(
                    (float)
                    $sellers->sum(
                        'sales_amount'
                    ),
                    2
                ),

            'collected_amount' =>
                round(
                    (float)
                    $sellers->sum(
                        'collected_amount'
                    ),
                    2
                ),

            'outstanding_amount' =>
                round(
                    (float)
                    $sellers->sum(
                        'outstanding_amount'
                    ),
                    2
                ),
        ];

        $batches =
            $this->tenant(
                HotspotBatch::query(),
                $tenantId
            )
                ->with([
                    'plan:id,name',
                    'seller:id,code,name',
                ])
                ->withCount([
                    'vouchers',
                    'vouchers as sold_count' =>
                        fn ($query) =>
                            $query
                                ->whereNotNull(
                                    'sold_at'
                                ),
                ])
                ->latest('id')
                ->limit(200)
                ->get()
                ->map(
                    fn (
                        HotspotBatch $batch
                    ): array => [
                        'id' =>
                            $batch->id,

                        'batch_code' =>
                            $batch->batch_code,

                        'quantity' =>
                            (int)
                            $batch->quantity,

                        'vouchers_count' =>
                            (int)
                            $batch
                                ->vouchers_count,

                        'sold_count' =>
                            (int)
                            $batch
                                ->sold_count,

                        'plan' =>
                            $batch
                                ->plan
                                ?->name,

                        'seller' =>
                            $batch->seller
                                ? [
                                    'id' =>
                                        $batch
                                            ->seller
                                            ->id,

                                    'code' =>
                                        $batch
                                            ->seller
                                            ->code,

                                    'name' =>
                                        $batch
                                            ->seller
                                            ->name,
                                ]
                                : null,
                    ]
                )
                ->values();

        $collections =
            $this->tenant(
                HotspotSellerCollection::query(),
                $tenantId
            )
                ->with([
                    'seller:id,code,name',
                    'collector:id,name',
                ])
                ->latest(
                    'collected_at'
                )
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(
                    fn (
                        HotspotSellerCollection $row
                    ): array => [
                        'id' =>
                            $row->id,

                        'seller' =>
                            $row->seller
                                ? [
                                    'code' =>
                                        $row
                                            ->seller
                                            ->code,

                                    'name' =>
                                        $row
                                            ->seller
                                            ->name,
                                ]
                                : null,

                        'amount' =>
                            (float)
                            $row->amount,

                        'collected_at' =>
                            $row
                                ->collected_at
                                ?->toIso8601String(),

                        'payment_method' =>
                            $row
                                ->payment_method,

                        'reference' =>
                            $row
                                ->reference,

                        'notes' =>
                            $row->notes,

                        'collected_by' =>
                            $row
                                ->collector
                                ?->name,
                    ]
                )
                ->values();

        $sales =
            DB::table(
                'hotspot_invoices as hi'
            )
                ->join(
                    'hotspot_sellers as hs',
                    'hs.id',
                    '=',
                    'hi.hotspot_seller_id'
                )
                ->join(
                    'hotspot_vouchers as hv',
                    'hv.id',
                    '=',
                    'hi.hotspot_voucher_id'
                )
                ->when(
                    $tenantId !== null,
                    fn ($query) =>
                        $query->where(
                            'hi.reseller_id',
                            $tenantId
                        ),
                    fn ($query) =>
                        $query->whereNull(
                            'hi.reseller_id'
                        )
                )
                ->where(
                    'hi.invoice_type',
                    'sale'
                )
                ->where(
                    'hi.status',
                    '!=',
                    'cancelled'
                )
                ->orderByDesc(
                    'hi.id'
                )
                ->limit(100)
                ->get([
                    'hi.id',
                    'hi.invoice_no',
                    'hi.amount',
                    'hi.discount',
                    'hi.service_from',
                    'hi.service_until',
                    'hs.code as seller_code',
                    'hs.name as seller_name',
                    'hv.username as voucher_code',
                ])
                ->map(
                    fn ($row): array => [
                        'id' =>
                            $row->id,

                        'invoice_no' =>
                            $row->invoice_no,

                        'amount' =>
                            round(
                                (float)
                                $row->amount
                                - (float)
                                $row->discount,
                                2
                            ),

                        'seller_code' =>
                            $row->seller_code,

                        'seller_name' =>
                            $row->seller_name,

                        'voucher_code' =>
                            $row->voucher_code,

                        'service_from' =>
                            $row
                                ->service_from,

                        'service_until' =>
                            $row
                                ->service_until,
                    ]
                )
                ->values();

        return Inertia::render(
            'Hotspot/Sellers',
            [
                'summary' =>
                    $summary,

                'sellers' =>
                    $sellers,

                'batches' =>
                    $batches,

                'collections' =>
                    $collections,

                'sales' =>
                    $sales,
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $this->manageAccess(
            $request
        );

        $tenantId =
            $this->tenantId(
                $request
            );

        $data =
            $request->validate([
                'code' => [
                    'nullable',
                    'string',
                    'max:40',
                ],

                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'phone' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        $code =
            strtoupper(
                trim(
                    (string) (
                        $data['code']
                        ?? ''
                    )
                )
            );

        if ($code === '') {
            do {
                $code =
                    'HS-'
                    . Str::upper(
                        Str::random(6)
                    );
            } while (
                HotspotSeller::query()
                    ->where(
                        'code',
                        $code
                    )
                    ->exists()
            );
        }

        if (
            HotspotSeller::query()
                ->where(
                    'code',
                    $code
                )
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'code' =>
                    'Seller code already exists.',
            ]);
        }

        HotspotSeller::create([
            'reseller_id' =>
                $tenantId,

            'code' =>
                $code,

            'name' =>
                trim(
                    $data['name']
                ),

            'phone' =>
                filled(
                    $data['phone']
                    ?? null
                )
                    ? trim(
                        $data['phone']
                    )
                    : null,

            'notes' =>
                filled(
                    $data['notes']
                    ?? null
                )
                    ? trim(
                        $data['notes']
                    )
                    : null,

            'active' =>
                true,
        ]);

        return back()->with(
            'success',
            'Hotspot seller created.'
        );
    }

    public function update(
        Request $request,
        int $seller
    ): RedirectResponse {
        $this->manageAccess(
            $request
        );

        $model =
            $this->seller(
                $request,
                $seller
            );

        $data =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'phone' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'active' => [
                    'required',
                    'boolean',
                ],
            ]);

        $model->update([
            'name' =>
                trim(
                    $data['name']
                ),

            'phone' =>
                filled(
                    $data['phone']
                    ?? null
                )
                    ? trim(
                        $data['phone']
                    )
                    : null,

            'notes' =>
                filled(
                    $data['notes']
                    ?? null
                )
                    ? trim(
                        $data['notes']
                    )
                    : null,

            'active' =>
                (bool)
                $data['active'],
        ]);

        return back()->with(
            'success',
            'Seller updated.'
        );
    }

    public function assignBatch(
        Request $request
    ): RedirectResponse {
        $this->manageAccess(
            $request
        );

        $tenantId =
            $this->tenantId(
                $request
            );

        $data =
            $request->validate([
                'batch_id' => [
                    'required',
                    'integer',
                ],

                'seller_id' => [
                    'required',
                    'integer',
                ],
            ]);

        $seller =
            $this->seller(
                $request,
                (int)
                $data['seller_id']
            );

        if (!$seller->active) {
            throw ValidationException::withMessages([
                'seller_id' =>
                    'Selected seller is inactive.',
            ]);
        }

        $batch =
            $this->tenant(
                HotspotBatch::query(),
                $tenantId
            )
                ->findOrFail(
                    (int)
                    $data['batch_id']
                );

        $sold =
            HotspotVoucher::withoutGlobalScopes()
                ->where(
                    'hotspot_batch_id',
                    $batch->id
                )
                ->whereNotNull(
                    'sold_at'
                )
                ->count();

        if (
            $sold > 0
            && (int)
                $batch->hotspot_seller_id
                !== $seller->id
        ) {
            throw ValidationException::withMessages([
                'batch_id' =>
                    'This batch already contains sold vouchers. Reassign individual unused vouchers instead.',
            ]);
        }

        DB::transaction(
            function () use (
                $batch,
                $seller,
                $tenantId
            ): void {
                $batch->forceFill([
                    'hotspot_seller_id' =>
                        $seller->id,
                ])->save();

                HotspotVoucher::withoutGlobalScopes()
                    ->where(
                        'hotspot_batch_id',
                        $batch->id
                    )
                    ->when(
                        $tenantId !== null,
                        fn ($query) =>
                            $query->where(
                                'reseller_id',
                                $tenantId
                            ),
                        fn ($query) =>
                            $query->whereNull(
                                'reseller_id'
                            )
                    )
                    ->whereNull(
                        'sold_at'
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->update([
                        'hotspot_seller_id' =>
                            $seller->id,

                        'updated_at' =>
                            now(),
                    ]);
            }
        );

        return back()->with(
            'success',
            'Voucher batch assigned to seller.'
        );
    }

    public function assignVoucher(
        Request $request
    ): RedirectResponse {
        $this->manageAccess(
            $request
        );

        $tenantId =
            $this->tenantId(
                $request
            );

        $data =
            $request->validate([
                'voucher_code' => [
                    'required',
                    'digits:6',
                ],

                'seller_id' => [
                    'required',
                    'integer',
                ],
            ]);

        $seller =
            $this->seller(
                $request,
                (int)
                $data['seller_id']
            );

        if (!$seller->active) {
            throw ValidationException::withMessages([
                'seller_id' =>
                    'Selected seller is inactive.',
            ]);
        }

        $voucher =
            $this->tenant(
                HotspotVoucher::withoutGlobalScopes()
                    ->whereNull(
                        'deleted_at'
                    ),
                $tenantId
            )
                ->where(
                    'username',
                    $data[
                        'voucher_code'
                    ]
                )
                ->firstOrFail();

        if (
            $voucher->sold_at
            || $voucher->status
                !== 'unused'
        ) {
            throw ValidationException::withMessages([
                'voucher_code' =>
                    'Only an unused unsold voucher can be reassigned.',
            ]);
        }

        $voucher->forceFill([
            'hotspot_seller_id' =>
                $seller->id,
        ])->save();

        return back()->with(
            'success',
            'Voucher assigned to seller.'
        );
    }

    public function collect(
        Request $request,
        int $seller
    ): RedirectResponse {
        $this->paymentAccess(
            $request
        );

        $model =
            $this->seller(
                $request,
                $seller
            );

        $data =
            $request->validate([
                'amount' => [
                    'required',
                    'numeric',
                    'min:0.01',
                ],

                'payment_method' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'reference' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        DB::transaction(
            function () use (
                $request,
                $model,
                $data
            ): void {
                $locked =
                    HotspotSeller::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $model->id
                        );

                $stats =
                    $this->sellerStats(
                        $locked->id
                    );

                $amount =
                    round(
                        (float)
                        $data['amount'],
                        2
                    );

                if (
                    $amount
                    > $stats[
                        'outstanding_amount'
                    ] + 0.001
                ) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'Collection cannot exceed seller outstanding QAR '
                            . number_format(
                                $stats[
                                    'outstanding_amount'
                                ],
                                2
                            )
                            . '.',
                    ]);
                }

                HotspotSellerCollection::create([
                    'reseller_id' =>
                        $locked
                            ->reseller_id,

                    'hotspot_seller_id' =>
                        $locked->id,

                    'amount' =>
                        $amount,

                    'collected_at' =>
                        Carbon::now(
                            'Asia/Qatar'
                        ),

                    'payment_method' =>
                        trim(
                            $data[
                                'payment_method'
                            ]
                        ),

                    'reference' =>
                        filled(
                            $data[
                                'reference'
                            ] ?? null
                        )
                            ? trim(
                                $data[
                                    'reference'
                                ]
                            )
                            : null,

                    'notes' =>
                        filled(
                            $data[
                                'notes'
                            ] ?? null
                        )
                            ? trim(
                                $data[
                                    'notes'
                                ]
                            )
                            : null,

                    'collected_by' =>
                        $request
                            ->user()
                            ?->id,
                ]);
            }
        );

        return back()->with(
            'success',
            'Seller collection recorded.'
        );
    }

    private function sellerStats(
        int $sellerId
    ): array {
        $assigned =
            DB::table(
                'hotspot_vouchers'
            )
                ->where(
                    'hotspot_seller_id',
                    $sellerId
                )
                ->count();

        $unsold =
            DB::table(
                'hotspot_vouchers'
            )
                ->where(
                    'hotspot_seller_id',
                    $sellerId
                )
                ->whereNull(
                    'deleted_at'
                )
                ->whereNull(
                    'sold_at'
                )
                ->count();

        $saleQuery =
            DB::table(
                'hotspot_invoices'
            )
                ->where(
                    'hotspot_seller_id',
                    $sellerId
                )
                ->where(
                    'invoice_type',
                    'sale'
                )
                ->where(
                    'status',
                    '!=',
                    'cancelled'
                );

        $soldCount =
            (clone $saleQuery)
                ->count();

        $salesAmount =
            round(
                (float)
                (clone $saleQuery)
                    ->selectRaw(
                        'COALESCE(SUM(amount - discount), 0) AS total'
                    )
                    ->value(
                        'total'
                    ),
                2
            );

        $collected =
            round(
                (float)
                DB::table(
                    'hotspot_seller_collections'
                )
                    ->where(
                        'hotspot_seller_id',
                        $sellerId
                    )
                    ->sum(
                        'amount'
                    ),
                2
            );

        return [
            'assigned_count' =>
                $assigned,

            'unsold_count' =>
                $unsold,

            'sold_count' =>
                $soldCount,

            'sales_amount' =>
                $salesAmount,

            'collected_amount' =>
                $collected,

            'outstanding_amount' =>
                max(
                    0,
                    round(
                        $salesAmount
                        - $collected,
                        2
                    )
                ),
        ];
    }

    private function seller(
        Request $request,
        int $id
    ): HotspotSeller {
        return $this->tenant(
            HotspotSeller::query(),
            $this->tenantId(
                $request
            )
        )->findOrFail(
            $id
        );
    }

    private function tenant(
        Builder $query,
        ?int $tenantId
    ): Builder {
        return $tenantId !== null
            ? $query->where(
                'reseller_id',
                $tenantId
            )
            : $query->whereNull(
                'reseller_id'
            );
    }

    private function tenantId(
        Request $request
    ): ?int {
        $id =
            $request
                ->user()
                ?->reseller_id;

        return $id
            ? (int) $id
            : null;
    }

    private function viewAccess(
        Request $request
    ): void {
        $user =
            $request->user();

        abort_unless(
            $user
            && (
                $user->isSuperAdmin()
                || $user->isResellerOwner()
                || $user->isManager()
                || $user->hasAnyPermission([
                    'hotspot.manage',
                    'hotspot.payments',
                ])
            ),
            403
        );
    }

    private function manageAccess(
        Request $request
    ): void {
        $user =
            $request->user();

        abort_unless(
            $user
            && (
                $user->isSuperAdmin()
                || $user->isResellerOwner()
                || $user->isManager()
                || $user->hasPermission(
                    'hotspot.manage'
                )
            ),
            403
        );
    }

    private function paymentAccess(
        Request $request
    ): void {
        $user =
            $request->user();

        abort_unless(
            $user
            && (
                $user->isSuperAdmin()
                || $user->isResellerOwner()
                || $user->isManager()
                || $user->hasAnyPermission([
                    'hotspot.manage',
                    'hotspot.payments',
                ])
            ),
            403
        );
    }
}
