<?php

namespace App\Http\Controllers\SuperAdmin\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\Hotel;
use App\Models\Hotel\HotelPlan;
use App\Models\Hotel\HotelSubscription;
use App\Models\Hotel\HotelUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HotelController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->access(
            $request
        );

        return Inertia::render(
            'SuperAdmin/HotelHotspot/Hotels/Index',
            [
                'hotels' =>
                    Hotel::query()
                        ->with([
                            'activeSubscription.plan',
                        ])
                        ->withCount([
                            'users',
                        ])
                        ->latest('id')
                        ->get(),
            ]
        );
    }

    public function create(
        Request $request
    ): Response {
        $this->access(
            $request
        );

        return Inertia::render(
            'SuperAdmin/HotelHotspot/Hotels/Create',
            [
                'plans' =>
                    HotelPlan::query()
                        ->where(
                            'active',
                            true
                        )
                        ->orderBy('price')
                        ->get(),
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $this->access(
            $request
        );

        $data =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'owner_name' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                ],

                'phone' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'whatsapp' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'address' => [
                    'nullable',
                    'string',
                    'max:1500',
                ],

                'timezone' => [
                    'required',
                    'timezone',
                ],

                'currency' => [
                    'required',
                    'string',
                    'max:10',
                ],

                'check_out_time' => [
                    'required',
                    'date_format:H:i',
                ],

                'plan_id' => [
                    'required',
                    Rule::exists(
                        'hotel_plans',
                        'id'
                    )->where(
                        fn ($query) =>
                            $query->where(
                                'active',
                                true
                            )
                    ),
                ],

                'admin_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'admin_email' => [
                    'required',
                    'email',
                    'max:255',
                    Rule::unique(
                        'hotel_users',
                        'email'
                    ),
                ],

                'admin_password' => [
                    'required',
                    'string',
                    'min:8',
                    'max:255',
                    'confirmed',
                ],
            ]);

        $hotel =
            DB::transaction(
                function () use (
                    $request,
                    $data
                ): Hotel {
                    $plan =
                        HotelPlan::query()
                            ->findOrFail(
                                $data['plan_id']
                            );

                    $hotel =
                        Hotel::query()
                            ->create([
                                'code' =>
                                    $this
                                        ->hotelCode(),

                                'slug' =>
                                    $this
                                        ->hotelSlug(
                                            $data['name']
                                        ),

                                'name' =>
                                    trim(
                                        $data['name']
                                    ),

                                'status' =>
                                    'active',

                                'owner_name' =>
                                    $data[
                                        'owner_name'
                                    ]
                                    ?? null,

                                'email' =>
                                    $data[
                                        'email'
                                    ]
                                    ?? null,

                                'phone' =>
                                    $data[
                                        'phone'
                                    ]
                                    ?? null,

                                'whatsapp' =>
                                    $data[
                                        'whatsapp'
                                    ]
                                    ?? null,

                                'address' =>
                                    $data[
                                        'address'
                                    ]
                                    ?? null,

                                'timezone' =>
                                    $data[
                                        'timezone'
                                    ],

                                'currency' =>
                                    strtoupper(
                                        $data[
                                            'currency'
                                        ]
                                    ),

                                'check_out_time' =>
                                    $data[
                                        'check_out_time'
                                    ],

                                'portal_title' =>
                                    'Welcome to '
                                    . trim(
                                        $data['name']
                                    )
                                    . ' Guest WiFi',

                                'enabled_locales' =>
                                    ['en'],

                                'created_by' =>
                                    $request
                                        ->user()
                                        ->id,
                            ]);

                    $this
                        ->newSubscription(
                            $hotel,
                            $plan,
                            $request
                                ->user()
                                ->id
                        );

                    HotelUser::query()
                        ->create([
                            'hotel_id' =>
                                $hotel->id,

                            'name' =>
                                trim(
                                    $data[
                                        'admin_name'
                                    ]
                                ),

                            'email' =>
                                strtolower(
                                    trim(
                                        $data[
                                            'admin_email'
                                        ]
                                    )
                                ),

                            'password' =>
                                $data[
                                    'admin_password'
                                ],

                            'role' =>
                                'admin',

                            'permissions' =>
                                [],

                            'is_active' =>
                                true,
                        ]);

                    return $hotel;
                }
            );

        return redirect()
            ->route(
                'superadmin.hotel.hotels.edit',
                $hotel
            )
            ->with(
                'success',
                'Hotel created.'
            );
    }

    public function edit(
        Request $request,
        Hotel $hotel
    ): Response {
        $this->access(
            $request
        );

        $hotel->load([
            'activeSubscription.plan',
        ]);

        return Inertia::render(
            'SuperAdmin/HotelHotspot/Hotels/Edit',
            [
                'hotel' =>
                    $hotel,

                'admin' =>
                    HotelUser::query()
                        ->where(
                            'hotel_id',
                            $hotel->id
                        )
                        ->where(
                            'role',
                            'admin'
                        )
                        ->oldest('id')
                        ->first([
                            'id',
                            'name',
                            'email',
                            'is_active',
                        ]),

                'plans' =>
                    HotelPlan::query()
                        ->where(
                            'active',
                            true
                        )
                        ->orderBy('price')
                        ->get(),

                'subscriptions' =>
                    HotelSubscription::query()
                        ->with('plan')
                        ->where(
                            'hotel_id',
                            $hotel->id
                        )
                        ->latest('id')
                        ->limit(50)
                        ->get(),
            ]
        );
    }

    public function update(
        Request $request,
        Hotel $hotel
    ): RedirectResponse {
        $this->access(
            $request
        );

        $data =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'status' => [
                    'required',
                    Rule::in([
                        'active',
                        'suspended',
                    ]),
                ],

                'owner_name' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                ],

                'phone' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'whatsapp' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'address' => [
                    'nullable',
                    'string',
                    'max:1500',
                ],

                'timezone' => [
                    'required',
                    'timezone',
                ],

                'currency' => [
                    'required',
                    'string',
                    'max:10',
                ],

                'check_out_time' => [
                    'required',
                    'date_format:H:i',
                ],
            ]);

        $oldStatus =
            $hotel->status;

        $hotel->fill([
            ...$data,

            'currency' =>
                strtoupper(
                    $data['currency']
                ),
        ]);

        if (
            $oldStatus !== 'suspended'
            && $data['status']
                === 'suspended'
        ) {
            $hotel->suspended_at =
                now();
        }

        if (
            $data['status']
            === 'active'
        ) {
            $hotel->suspended_at =
                null;

            $hotel->suspension_reason =
                null;
        }

        $hotel->save();

        return back()->with(
            'success',
            'Hotel updated.'
        );
    }

    public function subscription(
        Request $request,
        Hotel $hotel
    ): RedirectResponse {
        $this->access(
            $request
        );

        $data =
            $request->validate([
                'plan_id' => [
                    'required',
                    Rule::exists(
                        'hotel_plans',
                        'id'
                    )->where(
                        fn ($query) =>
                            $query->where(
                                'active',
                                true
                            )
                    ),
                ],
            ]);

        DB::transaction(
            function () use (
                $request,
                $hotel,
                $data
            ): void {
                HotelSubscription::query()
                    ->where(
                        'hotel_id',
                        $hotel->id
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->update([
                        'status' =>
                            'replaced',
                    ]);

                $plan =
                    HotelPlan::query()
                        ->findOrFail(
                            $data['plan_id']
                        );

                $this
                    ->newSubscription(
                        $hotel,
                        $plan,
                        $request
                            ->user()
                            ->id
                    );
            }
        );

        return back()->with(
            'success',
            'Hotel subscription changed.'
        );
    }

    private function newSubscription(
        Hotel $hotel,
        HotelPlan $plan,
        int $createdBy
    ): HotelSubscription {
        $start =
            now();

        return HotelSubscription::query()
            ->create([
                'hotel_id' =>
                    $hotel->id,

                'hotel_plan_id' =>
                    $plan->id,

                'status' =>
                    'active',

                'guest_limit' =>
                    $plan->guest_limit,

                'is_guest_unlimited' =>
                    $plan
                        ->is_guest_unlimited,

                'router_limit' =>
                    $plan->router_limit,

                'is_router_unlimited' =>
                    $plan
                        ->is_router_unlimited,

                'receptionist_limit' =>
                    $plan
                        ->receptionist_limit,

                'is_receptionist_unlimited' =>
                    $plan
                        ->is_receptionist_unlimited,

                'concurrent_limit' =>
                    $plan
                        ->concurrent_limit,

                'price' =>
                    $plan->price,

                'starts_at' =>
                    $start,

                'expires_at' =>
                    $start
                        ->copy()
                        ->addDays(
                            max(
                                1,
                                $plan
                                    ->validity_days
                            )
                        ),

                'created_by' =>
                    $createdBy,
            ]);
    }

    private function hotelCode(): string
    {
        do {
            $code =
                'HTL-'
                . Str::upper(
                    Str::random(8)
                );
        } while (
            Hotel::query()
                ->where(
                    'code',
                    $code
                )
                ->exists()
        );

        return $code;
    }

    private function hotelSlug(
        string $name
    ): string {
        $base =
            Str::slug(
                $name
            );

        if ($base === '') {
            $base = 'hotel';
        }

        $slug = $base;
        $counter = 1;

        while (
            Hotel::query()
                ->where(
                    'slug',
                    $slug
                )
                ->exists()
        ) {
            $counter++;

            $slug =
                $base
                . '-'
                . $counter;
        }

        return $slug;
    }

    private function access(
        Request $request
    ): void {
        abort_unless(
            $request->user()
            && $request
                ->user()
                ->is_active
            && $request
                ->user()
                ->isSuperAdmin(),
            403
        );
    }
}
