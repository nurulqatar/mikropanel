<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\HotelUser;
use App\Models\Hotel\HotelVoucher;
use App\Services\Hotel\HotelGuestVoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VoucherController extends Controller
{
    public function index(): Response
    {
        $user =
            $this->accessAny([
                'vouchers.issue',
                'vouchers.print',
                'reports.view',
            ]);

        return Inertia::render(
            'Hotel/Vouchers/Index',
            [
                'vouchers' =>
                    HotelVoucher::query()
                        ->with([
                            'stay.guest',
                            'profile',
                            'routerSyncs.router',
                        ])
                        ->where(
                            'hotel_id',
                            $user->hotel_id
                        )
                        ->latest('id')
                        ->limit(500)
                        ->get(),
            ]
        );
    }

    public function create(): Response
    {
        $user =
            $this->accessAny([
                'vouchers.issue',
            ]);

        return Inertia::render(
            'Hotel/Vouchers/Create',
            [
                'hotel' => [
                    'name' =>
                        $user
                            ->hotel
                            ->name,

                    'check_out_time' =>
                        substr(
                            (string)
                            $user
                                ->hotel
                                ->check_out_time,
                            0,
                            5
                        ),
                ],
            ]
        );
    }

    public function store(
        Request $request,
        HotelGuestVoucherService $service
    ): RedirectResponse {
        $user =
            $this->accessAny([
                'vouchers.issue',
            ]);

        $data =
            $request->validate([
                'room_number' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'check_in_date' => [
                    'required',
                    'date_format:Y-m-d',
                ],

                'check_out_date' => [
                    'required',
                    'date_format:Y-m-d',
                ],

                'phone' => [
                    'required',
                    'string',
                    'max:100',
                    'regex:/^\+?[0-9][0-9\s\-\(\)]{5,30}$/',
                ],

                'phone_country' => [
                    'nullable',
                    'string',
                    'max:10',
                ],

                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'identity_type' => [
                    'required',
                    Rule::in([
                        'passport',
                        'qid',
                    ]),
                ],

                'identity_number' => [
                    'required',
                    'string',
                    'max:100',
                ],

                'nationality' => [
                    'required',
                    'string',
                    'max:120',
                ],

                'preferred_locale' => [
                    'nullable',
                    'string',
                    'max:20',
                ],

                'terms_accepted' => [
                    'accepted',
                ],
            ]);

        $voucher =
            $service->issue(
                hotel:
                    $user->hotel,

                data:
                    $data,

                creator:
                    $user,

                source:
                    'reception',

                ipAddress:
                    $request->ip()
            );

        return redirect()
            ->route(
                'hotel.vouchers.index'
            )
            ->with(
                'success',
                'Voucher '
                . $voucher->username
                . ' created.'
            );
    }

    public function print(
        HotelVoucher $voucher
    ) {
        $user =
            $this->accessAny([
                'vouchers.print',
            ]);

        abort_unless(
            $voucher->hotel_id
                === $user->hotel_id,
            404
        );

        $voucher->load([
            'hotel',
            'stay.guest',
            'profile',
        ]);

        return view(
            'hotel.vouchers.print',
            [
                'voucher' =>
                    $voucher,
            ]
        );
    }

    private function accessAny(
        array $permissions
    ): HotelUser {
        $user =
            Auth::guard('hotel')
                ->user();

        abort_unless(
            $user,
            401
        );

        if ($user->isAdmin()) {
            return $user;
        }

        foreach (
            $permissions
            as $permission
        ) {
            if (
                $user->hasPermission(
                    $permission
                )
            ) {
                return $user;
            }
        }

        abort(403);
    }
}
