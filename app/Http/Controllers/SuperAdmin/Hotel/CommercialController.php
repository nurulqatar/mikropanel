<?php

namespace App\Http\Controllers\SuperAdmin\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\Hotel;
use App\Models\Hotel\HotelBillingInvoice;
use App\Models\Hotel\HotelBillingPayment;
use App\Models\Hotel\HotelNotification;
use App\Services\Hotel\HotelCommercialService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CommercialController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->superAdmin(
            $request
        );

        return Inertia::render(
            'SuperAdmin/HotelHotspot/Commercial',
            [
                'hotels' =>
                    Hotel::query()
                        ->with(
                            'activeSubscription'
                        )
                        ->orderBy('name')
                        ->get(),

                'invoices' =>
                    HotelBillingInvoice::query()
                        ->with(
                            'hotel:id,name,code,currency,status'
                        )
                        ->latest('id')
                        ->limit(1000)
                        ->get(),

                'payments' =>
                    HotelBillingPayment::query()
                        ->with(
                            'hotel:id,name'
                        )
                        ->latest('id')
                        ->limit(500)
                        ->get(),

                'alerts' =>
                    HotelNotification::query()
                        ->with(
                            'hotel:id,name'
                        )
                        ->whereNull(
                            'resolved_at'
                        )
                        ->latest('id')
                        ->limit(500)
                        ->get(),

                'stats' => [
                    'hotels' =>
                        Hotel::query()
                            ->count(),

                    'active_hotels' =>
                        Hotel::query()
                            ->where(
                                'status',
                                'active'
                            )
                            ->count(),

                    'due' =>
                        (float)
                        HotelBillingInvoice::query()
                            ->sum(
                                'due_amount'
                            ),

                    'collected' =>
                        (float)
                        HotelBillingPayment::query()
                            ->sum(
                                'amount'
                            ),
                ],
            ]
        );
    }

    public function payment(
        Request $request,
        HotelBillingInvoice $invoice,
        HotelCommercialService $commercial
    ): RedirectResponse {
        $admin =
            $this->superAdmin(
                $request
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
                    'max:191',
                ],

                'notes' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],
            ]);

        DB::transaction(
            function () use (
                $invoice,
                $data,
                $admin,
                $request,
                $commercial
            ): void {
                $locked =
                    HotelBillingInvoice::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $invoice->id
                        );

                $amount =
                    min(
                        (float)
                        $data['amount'],
                        (float)
                        $locked->due_amount
                    );

                abort_if(
                    $amount <= 0,
                    422,
                    'Invoice has no outstanding balance.'
                );

                HotelBillingPayment::query()
                    ->create([
                        'hotel_id' =>
                            $locked->hotel_id,

                        'hotel_billing_invoice_id' =>
                            $locked->id,

                        'amount' =>
                            $amount,

                        'payment_date' =>
                            today(),

                        'payment_method' =>
                            $data[
                                'payment_method'
                            ],

                        'reference' =>
                            $data[
                                'reference'
                            ] ?? null,

                        'notes' =>
                            $data[
                                'notes'
                            ] ?? null,

                        'received_by' =>
                            $admin->id,
                    ]);

                $paid =
                    (float)
                    $locked->paid_amount
                    + $amount;

                $due =
                    max(
                        0,
                        (float)
                        $locked->amount
                        - $paid
                    );

                $locked->forceFill([
                    'paid_amount' =>
                        $paid,

                    'due_amount' =>
                        $due,

                    'status' =>
                        $due <= 0
                            ? 'paid'
                            : 'partial',

                    'paid_at' =>
                        $due <= 0
                            ? now()
                            : null,
                ])->save();

                $commercial->audit(
                    $locked->hotel_id,
                    null,
                    'super_admin',
                    $admin->name
                    ?? $admin->email,
                    'hotel.billing.payment',
                    'hotel_billing_invoice',
                    $locked->id,
                    'Hotel subscription payment recorded.',
                    [
                        'amount' =>
                            $amount,

                        'payment_method' =>
                            $data[
                                'payment_method'
                            ],
                    ],
                    $request->ip()
                );
            }
        );

        return back()->with(
            'success',
            'Hotel payment recorded.'
        );
    }

    public function renew(
        Request $request,
        Hotel $hotel,
        HotelCommercialService $commercial
    ): RedirectResponse {
        $admin =
            $this->superAdmin(
                $request
            );

        $subscription =
            $commercial->renewHotel(
                $hotel,
                $admin->id
            );

        $commercial->audit(
            $hotel->id,
            null,
            'super_admin',
            $admin->name
            ?? $admin->email,
            'hotel.subscription.renew',
            'hotel_subscription',
            $subscription->id,
            'Hotel subscription renewed.',
            [],
            $request->ip()
        );

        return back()->with(
            'success',
            'Hotel subscription renewed.'
        );
    }

    private function superAdmin(
        Request $request
    ) {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->isSuperAdmin(),
            403
        );

        return $user;
    }
}
