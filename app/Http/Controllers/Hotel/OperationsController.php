<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Jobs\Hotel\SyncHotelVoucherToRouters;
use App\Models\Hotel\HotelAuditLog;
use App\Models\Hotel\HotelBillingInvoice;
use App\Models\Hotel\HotelHotspotSession;
use App\Models\Hotel\HotelNotification;
use App\Models\Hotel\HotelRouter;
use App\Models\Hotel\HotelUser;
use App\Models\Hotel\HotelVoucher;
use App\Services\Hotel\HotelMikroTikSessionControlService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsController extends Controller
{
    public function index(): Response
    {
        $user =
            $this->viewer();

        $hotelId =
            $user->hotel_id;

        return Inertia::render(
            'Hotel/Operations/Index',
            [
                'stats' => [
                    'online_users' =>
                        HotelHotspotSession::query()
                            ->where(
                                'hotel_id',
                                $hotelId
                            )
                            ->where(
                                'is_online',
                                true
                            )
                            ->count(),

                    'active_vouchers' =>
                        HotelVoucher::query()
                            ->where(
                                'hotel_id',
                                $hotelId
                            )
                            ->where(
                                'expires_at',
                                '>',
                                now()
                            )
                            ->whereNotIn(
                                'status',
                                [
                                    'expired',
                                    'revoked',
                                ]
                            )
                            ->count(),

                    'offline_routers' =>
                        HotelRouter::query()
                            ->where(
                                'hotel_id',
                                $hotelId
                            )
                            ->where(
                                'enabled',
                                true
                            )
                            ->where(
                                function ($query): void {
                                    $query
                                        ->whereNotNull(
                                            'last_error'
                                        )
                                        ->orWhereIn(
                                            'status',
                                            [
                                                'failed',
                                                'offline',
                                                'error',
                                            ]
                                        );
                                }
                            )
                            ->count(),

                    'unread_alerts' =>
                        HotelNotification::query()
                            ->where(
                                'hotel_id',
                                $hotelId
                            )
                            ->whereNull(
                                'read_at'
                            )
                            ->count(),

                    'billing_due' =>
                        (float)
                        HotelBillingInvoice::query()
                            ->where(
                                'hotel_id',
                                $hotelId
                            )
                            ->where(
                                'due_amount',
                                '>',
                                0
                            )
                            ->sum(
                                'due_amount'
                            ),
                ],

                'routers' =>
                    HotelRouter::query()
                        ->where(
                            'hotel_id',
                            $hotelId
                        )
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'host',
                            'enabled',
                            'status',
                            'last_tested_at',
                            'last_error',
                        ]),

                'vouchers' =>
                    HotelVoucher::query()
                        ->with([
                            'stay.guest',
                            'routerSyncs.router',
                        ])
                        ->where(
                            'hotel_id',
                            $hotelId
                        )
                        ->latest('id')
                        ->limit(200)
                        ->get(),

                'notifications' =>
                    HotelNotification::query()
                        ->where(
                            'hotel_id',
                            $hotelId
                        )
                        ->latest('id')
                        ->limit(100)
                        ->get(),

                'audits' =>
                    HotelAuditLog::query()
                        ->where(
                            'hotel_id',
                            $hotelId
                        )
                        ->latest('id')
                        ->limit(100)
                        ->get(),

                'can_manage_vouchers' =>
                    $user->isAdmin()
                    || $user
                        ->hasPermission(
                            'vouchers.issue'
                        ),
            ]
        );
    }

    public function revoke(
        HotelVoucher $voucher
    ): RedirectResponse {
        $user =
            $this->manager();

        $this->owned(
            $voucher,
            $user
        );

        DB::transaction(
            function () use (
                $voucher
            ): void {
                $voucher->forceFill([
                    'status' =>
                        'revoked',
                ])->save();

                SyncHotelVoucherToRouters::dispatch(
                    $voucher->id,
                    'expire'
                )->afterCommit();
            }
        );

        return back()->with(
            'success',
            'Voucher revoked on all Hotel routers.'
        );
    }

    public function reactivate(
        HotelVoucher $voucher
    ): RedirectResponse {
        $user =
            $this->manager();

        $this->owned(
            $voucher,
            $user
        );

        abort_if(
            !$voucher->expires_at
            || $voucher
                ->expires_at
                ->lte(now()),
            422,
            'Voucher has already expired.'
        );

        DB::transaction(
            function () use (
                $voucher
            ): void {
                $voucher->forceFill([
                    'status' =>
                        'active',
                ])->save();

                SyncHotelVoucherToRouters::dispatch(
                    $voucher->id,
                    'upsert'
                )->afterCommit();
            }
        );

        return back()->with(
            'success',
            'Voucher reactivated.'
        );
    }

    public function extend(
        Request $request,
        HotelVoucher $voucher
    ): RedirectResponse {
        $user =
            $this->manager();

        $this->owned(
            $voucher,
            $user
        );

        $data =
            $request->validate([
                'checkout_date' => [
                    'required',
                    'date_format:Y-m-d',
                ],
            ]);

        $hotel =
            $user->hotel;

        $clock =
            substr(
                (string)
                $hotel->check_out_time,
                0,
                8
            );

        if (strlen($clock) === 5) {
            $clock .= ':00';
        }

        $checkout =
            CarbonImmutable::createFromFormat(
                'Y-m-d H:i:s',
                $data['checkout_date']
                . ' '
                . $clock,
                $hotel->timezone
                ?: 'Asia/Qatar'
            )->utc();

        abort_if(
            $checkout->lte(now()),
            422,
            'Checkout must be in the future.'
        );

        DB::transaction(
            function () use (
                $voucher,
                $checkout
            ): void {
                if ($voucher->stay) {
                    $voucher->stay
                        ->forceFill([
                            'check_out_at' =>
                                $checkout,

                            'status' =>
                                'active',
                        ])
                        ->save();
                }

                $voucher->forceFill([
                    'expires_at' =>
                        $checkout,

                    'status' =>
                        'active',
                ])->save();

                SyncHotelVoucherToRouters::dispatch(
                    $voucher->id,
                    'upsert'
                )->afterCommit();
            }
        );

        return back()->with(
            'success',
            'Voucher checkout and expiry extended.'
        );
    }

    public function disconnect(
        HotelVoucher $voucher,
        HotelMikroTikSessionControlService $service
    ): RedirectResponse {
        $user =
            $this->manager();

        $this->owned(
            $voucher,
            $user
        );

        $result =
            $service->disconnect(
                $voucher
            );

        $message =
            'Disconnected sessions: '
            . $result['disconnected'];

        if ($result['failures'] > 0) {
            $message .=
                '. Router failures: '
                . $result['failures'];
        }

        return back()->with(
            $result['failures'] > 0
                ? 'warning'
                : 'success',
            $message
        );
    }

    public function readNotification(
        HotelNotification $notification
    ): RedirectResponse {
        $user =
            $this->viewer();

        abort_unless(
            $notification->hotel_id
                === $user->hotel_id,
            404
        );

        $notification->forceFill([
            'read_at' =>
                now(),
        ])->save();

        return back();
    }

    public function auditCsv(): StreamedResponse
    {
        $user =
            $this->viewer();

        $rows =
            HotelAuditLog::query()
                ->where(
                    'hotel_id',
                    $user->hotel_id
                )
                ->latest('id')
                ->limit(10000)
                ->get();

        return response()
            ->streamDownload(
                function () use (
                    $rows
                ): void {
                    $out =
                        fopen(
                            'php://output',
                            'w'
                        );

                    fputcsv(
                        $out,
                        [
                            'Time',
                            'Actor',
                            'Type',
                            'Action',
                            'Description',
                            'IP',
                        ]
                    );

                    foreach ($rows as $row) {
                        fputcsv(
                            $out,
                            [
                                $row
                                    ->created_at
                                    ?->toDateTimeString(),

                                $row
                                    ->actor_name,

                                $row
                                    ->actor_type,

                                $row
                                    ->action,

                                $row
                                    ->description,

                                $row
                                    ->ip_address,
                            ]
                        );
                    }

                    fclose($out);
                },
                'hotel-audit-'
                . now()->format('Ymd-His')
                . '.csv',
                [
                    'Content-Type' =>
                        'text/csv',
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
                )
            || $user
                ->hasPermission(
                    'vouchers.issue'
                )
            || $user
                ->hasPermission(
                    'routers.manage'
                ),
            403
        );

        return $user;
    }

    private function manager(): HotelUser
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
                    'vouchers.issue'
                ),
            403
        );

        return $user;
    }

    private function owned(
        HotelVoucher $voucher,
        HotelUser $user
    ): void {
        abort_unless(
            $voucher->hotel_id
                === $user->hotel_id,
            404
        );
    }
}
