<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\HotelBillingInvoice;
use App\Models\Hotel\HotelBillingPayment;
use App\Models\Hotel\HotelUser;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function index(): Response
    {
        $user =
            $this->viewer();

        $invoices =
            HotelBillingInvoice::query()
                ->with('payments')
                ->where(
                    'hotel_id',
                    $user->hotel_id
                )
                ->latest('id')
                ->limit(500)
                ->get();

        return Inertia::render(
            'Hotel/Billing/Index',
            [
                'invoices' =>
                    $invoices,

                'payments' =>
                    HotelBillingPayment::query()
                        ->where(
                            'hotel_id',
                            $user->hotel_id
                        )
                        ->latest('id')
                        ->limit(500)
                        ->get(),

                'stats' => [
                    'total' =>
                        (float)
                        $invoices->sum(
                            'amount'
                        ),

                    'paid' =>
                        (float)
                        $invoices->sum(
                            'paid_amount'
                        ),

                    'due' =>
                        (float)
                        $invoices->sum(
                            'due_amount'
                        ),

                    'overdue' =>
                        $invoices
                            ->where(
                                'status',
                                'overdue'
                            )
                            ->count(),
                ],
            ]
        );
    }

    private function viewer(): HotelUser
    {
        $user =
            Auth::guard('hotel')
                ->user();

        abort_unless(
            $user,
            401
        );

        abort_unless(
            $user->isAdmin()
            || $user
                ->hasPermission(
                    'reports.view'
                ),
            403
        );

        return $user;
    }
}
