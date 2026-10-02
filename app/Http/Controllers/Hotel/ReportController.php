<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\HotelHotspotSession;
use App\Models\Hotel\HotelRouter;
use App\Models\Hotel\HotelUser;
use App\Models\Hotel\HotelVoucher;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ReportController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $user =
            $this->user();

        [
            $from,
            $to,
            $fromDate,
            $toDate,
        ] = $this->range(
            $request,
            $user
        );

        $base =
            HotelHotspotSession::query()
                ->where(
                    'hotel_id',
                    $user->hotel_id
                )
                ->whereBetween(
                    'created_at',
                    [
                        $from,
                        $to,
                    ]
                );

        $live =
            HotelHotspotSession::query()
                ->with([
                    'router:id,name,host',
                    'voucher.stay.guest',
                ])
                ->where(
                    'hotel_id',
                    $user->hotel_id
                )
                ->where(
                    'is_online',
                    true
                )
                ->latest(
                    'last_seen_at'
                )
                ->get();

        $history =
            (clone $base)
                ->with([
                    'router:id,name,host',
                    'voucher.stay.guest',
                ])
                ->latest(
                    'last_seen_at'
                )
                ->limit(1000)
                ->get();

        $bytesIn =
            (int)
            (clone $base)
                ->sum(
                    'bytes_in'
                );

        $bytesOut =
            (int)
            (clone $base)
                ->sum(
                    'bytes_out'
                );

        $routers =
            HotelRouter::query()
                ->where(
                    'hotel_id',
                    $user->hotel_id
                )
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'host',
                    'enabled',
                    'status',
                ])
                ->map(
                    function (
                        HotelRouter $router
                    ) use (
                        $from,
                        $to
                    ): array {
                        $query =
                            HotelHotspotSession::query()
                                ->where(
                                    'hotel_router_id',
                                    $router->id
                                )
                                ->whereBetween(
                                    'created_at',
                                    [
                                        $from,
                                        $to,
                                    ]
                                );

                        $in =
                            (int)
                            (clone $query)
                                ->sum(
                                    'bytes_in'
                                );

                        $out =
                            (int)
                            (clone $query)
                                ->sum(
                                    'bytes_out'
                                );

                        return [
                            'id' =>
                                $router->id,

                            'name' =>
                                $router->name,

                            'host' =>
                                $router->host,

                            'enabled' =>
                                (bool)
                                $router->enabled,

                            'status' =>
                                $router->status,

                            'online' =>
                                HotelHotspotSession::query()
                                    ->where(
                                        'hotel_router_id',
                                        $router->id
                                    )
                                    ->where(
                                        'is_online',
                                        true
                                    )
                                    ->count(),

                            'sessions' =>
                                (clone $query)
                                    ->count(),

                            'total_bytes' =>
                                $in + $out,
                        ];
                    }
                )
                ->values();

        $guests =
            $history
                ->filter(
                    fn (
                        HotelHotspotSession $row
                    ) =>
                        $row
                            ->voucher
                            ?->stay
                            ?->guest
                )
                ->groupBy(
                    fn (
                        HotelHotspotSession $row
                    ) =>
                        $row
                            ->voucher
                            ->stay
                            ->guest
                            ->id
                )
                ->map(
                    function ($sessions): array {
                        $first =
                            $sessions->first();

                        $guest =
                            $first
                                ->voucher
                                ->stay
                                ->guest;

                        return [
                            'guest_id' =>
                                $guest->id,

                            'name' =>
                                $guest->name,

                            'room_number' =>
                                $first
                                    ->voucher
                                    ->stay
                                    ->room_number,

                            'sessions' =>
                                $sessions
                                    ->count(),

                            'total_bytes' =>
                                (int)
                                $sessions
                                    ->sum(
                                        'bytes_in'
                                    )
                                +
                                (int)
                                $sessions
                                    ->sum(
                                        'bytes_out'
                                    ),
                        ];
                    }
                )
                ->sortByDesc(
                    'total_bytes'
                )
                ->values()
                ->take(500)
                ->all();

        return Inertia::render(
            'Hotel/Reports/Index',
            [
                'filters' => [
                    'from' =>
                        $fromDate,

                    'to' =>
                        $toDate,
                ],

                'stats' => [
                    'online_users' =>
                        $live->count(),

                    'sessions' =>
                        (clone $base)
                            ->count(),

                    'unique_vouchers' =>
                        (clone $base)
                            ->whereNotNull(
                                'hotel_voucher_id'
                            )
                            ->distinct()
                            ->count(
                                'hotel_voucher_id'
                            ),

                    'active_vouchers' =>
                        HotelVoucher::query()
                            ->where(
                                'hotel_id',
                                $user->hotel_id
                            )
                            ->where(
                                'status',
                                'active'
                            )
                            ->where(
                                'expires_at',
                                '>',
                                now()
                            )
                            ->count(),

                    'total_bytes' =>
                        $bytesIn
                        + $bytesOut,
                ],

                'live' =>
                    $live,

                'history' =>
                    $history,

                'routers' =>
                    $routers,

                'guests' =>
                    $guests,
            ]
        );
    }

    public function csv(
        Request $request
    ): StreamedResponse {
        $user =
            $this->user();

        [
            $from,
            $to,
            $fromDate,
            $toDate,
        ] = $this->range(
            $request,
            $user
        );

        $rows =
            HotelHotspotSession::query()
                ->with([
                    'router:id,name',
                    'voucher.stay.guest',
                ])
                ->where(
                    'hotel_id',
                    $user->hotel_id
                )
                ->whereBetween(
                    'created_at',
                    [
                        $from,
                        $to,
                    ]
                )
                ->latest('id')
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
                            'Guest',
                            'Room',
                            'Voucher',
                            'Router',
                            'IP',
                            'MAC',
                            'Online',
                            'Uptime',
                            'Bytes In',
                            'Bytes Out',
                            'Started',
                            'Ended',
                        ]
                    );

                    foreach (
                        $rows
                        as $row
                    ) {
                        fputcsv(
                            $out,
                            [
                                $row
                                    ->voucher
                                    ?->stay
                                    ?->guest
                                    ?->name,

                                $row
                                    ->voucher
                                    ?->stay
                                    ?->room_number,

                                $row->username,

                                $row
                                    ->router
                                    ?->name,

                                $row
                                    ->ip_address,

                                $row
                                    ->mac_address,

                                $row
                                    ->is_online
                                    ? 'Yes'
                                    : 'No',

                                $row->uptime,

                                $row->bytes_in,

                                $row->bytes_out,

                                $row
                                    ->started_at
                                    ?->toDateTimeString(),

                                $row
                                    ->ended_at
                                    ?->toDateTimeString(),
                            ]
                        );
                    }

                    fclose($out);
                },
                'hotel-hotspot-'
                . $fromDate
                . '-'
                . $toDate
                . '.csv',
                [
                    'Content-Type' =>
                        'text/csv',
                ]
            );
    }

    private function user(): HotelUser
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
            || $user->hasPermission(
                'reports.view'
            ),
            403
        );

        return $user;
    }

    private function range(
        Request $request,
        HotelUser $user
    ): array {
        $timezone =
            $user
                ->hotel
                ?->timezone
            ?: 'Asia/Qatar';

        $today =
            CarbonImmutable::now(
                $timezone
            );

        try {
            $from =
                CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    $request
                        ->string('from')
                        ->toString()
                    ?: $today
                        ->subDays(30)
                        ->toDateString(),
                    $timezone
                )->startOfDay();
        } catch (Throwable) {
            $from =
                $today
                    ->subDays(30)
                    ->startOfDay();
        }

        try {
            $to =
                CarbonImmutable::createFromFormat(
                    '!Y-m-d',
                    $request
                        ->string('to')
                        ->toString()
                    ?: $today
                        ->toDateString(),
                    $timezone
                )->endOfDay();
        } catch (Throwable) {
            $to =
                $today->endOfDay();
        }

        if ($from->gt($to)) {
            [$from, $to] = [
                $to->startOfDay(),
                $from->endOfDay(),
            ];
        }

        return [
            $from->utc(),
            $to->utc(),
            $from->toDateString(),
            $to->toDateString(),
        ];
    }
}
