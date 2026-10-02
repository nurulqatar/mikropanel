<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\ClientProvisionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClientBulkRenewalController extends Controller
{
    public function store(
        Request $request,
        Client $client,
        ClientProvisionService $provision
    ): RedirectResponse {
        $data = $request->validate([
            'payment_status' => [
                'required',
                Rule::in([
                    'paid',
                    'due',
                ]),
            ],

            'payment_method' => [
                'nullable',
                'required_if:payment_status,paid',
                'string',
                Rule::in([
                    'Cash',
                    'Bank Transfer',
                    'bKash',
                    'Nagad',
                    'Rocket',
                    'Upay',
                    'Ooredoo Money',
                    'iPay',
                    'Stripe',
                    'PayPal',
                    'Manual Adjustment',
                ]),
            ],

            'transaction_id' => [
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

        $isPaid =
            $data['payment_status']
            === 'paid';

        /*
         * Receiving money requires the existing
         * payment permission.
         */
        if (
            $isPaid
            && !(
                auth()->user()
                    ?->hasPermission(
                        'payments.manage'
                    )
                ?? false
            )
        ) {
            return back()
                ->withErrors([
                    'payment_status' =>
                        'You do not have permission to receive payments. Select Due.',
                ]);
        }

        /*
         * Always resolve to the primary customer.
         */
        $primary =
            $client->parent_client_id
                ? Client::query()
                    ->findOrFail(
                        $client->parent_client_id
                    )
                : $client;

        $result = DB::transaction(
            function () use (
                $primary,
                $data,
                $isPaid
            ): array {
                /*
                 * Lock every device under this account.
                 */
                $devices =
                    Client::query()
                        ->with('package')
                        ->where(
                            function ($query) use (
                                $primary
                            ): void {
                                $query
                                    ->whereKey(
                                        $primary->id
                                    )
                                    ->orWhere(
                                        'parent_client_id',
                                        $primary->id
                                    );
                            }
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                if ($devices->isEmpty()) {
                    throw ValidationException::withMessages([
                        'renew_all' =>
                            'No device was found for this client.',
                    ]);
                }

                $deviceIds =
                    $devices
                        ->pluck('id')
                        ->values();

                /*
                 * Keep the same rule as normal renewal:
                 * old Due must be paid before another
                 * service period is added.
                 */
                $existingDue =
                    round(
                        (float)
                        Invoice::query()
                            ->whereIn(
                                'client_id',
                                $deviceIds
                            )
                            /*
                             * ACCOUNT_DUE_STATUS_HARDEN_V1
                             *
                             * Refunded service is cancelled
                             * service, not collectible due.
                             */
                            ->whereNotIn(
                                'status',
                                [
                                    'cancelled',
                                    'refunded',
                                ]
                            )
                            ->where(
                                'due_amount',
                                '>',
                                0
                            )
                            ->sum(
                                'due_amount'
                            ),
                        2
                    );

                if ($existingDue > 0) {
                    throw ValidationException::withMessages([
                        'renew_all' =>
                            'This account has existing due of QAR '
                            . number_format(
                                $existingDue,
                                2
                            )
                            . '. Pay the existing due first, then use Renew All Devices.',
                    ]);
                }

                $today =
                    Carbon::today(
                        'Asia/Qatar'
                    );

                $now =
                    Carbon::now(
                        'Asia/Qatar'
                    );

                $totalAmount = 0.0;
                $renewedIds = [];

                foreach (
                    $devices
                    as $device
                ) {
                    $package =
                        $device->package;

                    if (!$package) {
                        throw ValidationException::withMessages([
                            'renew_all' =>
                                (
                                    $device->device_label
                                    ?: $device->client_code
                                )
                                . ' does not have a package.',
                        ]);
                    }

                    $price =
                        round(
                            (float)
                            $package->price,
                            2
                        );

                    $validityDays =
                        (int)
                        $package
                            ->validity_days;

                    if ($price <= 0) {
                        throw ValidationException::withMessages([
                            'renew_all' =>
                                'Package price must be greater than zero for '
                                . (
                                    $device->device_label
                                    ?: $device->client_code
                                )
                                . '.',
                        ]);
                    }

                    if ($validityDays < 1) {
                        throw ValidationException::withMessages([
                            'renew_all' =>
                                'Package validity is missing for '
                                . (
                                    $device->device_label
                                    ?: $device->client_code
                                )
                                . '.',
                        ]);
                    }

                    $currentExpiry =
                        $device->expiry_date
                            ?->copy()
                            ->startOfDay();

                    /*
                     * Preserve existing renewal rule.
                     */
                    $startFromToday =
                        !$device->enabled
                        || !$currentExpiry
                        || $currentExpiry->lt(
                            $today
                        );

                    $baseDate =
                        $startFromToday
                            ? $today->copy()
                            : $currentExpiry->copy();

                    $newExpiry =
                        $validityDays === 30
                            ? $baseDate
                                ->copy()
                                ->addMonthNoOverflow()
                            : $baseDate
                                ->copy()
                                ->addDays(
                                    $validityDays
                                );

                    $invoice =
                        Invoice::create([
                            'client_id' =>
                                $device->id,

                            'invoice_no' =>
                                'INV-ALL-'
                                . $now->format(
                                    'YmdHis'
                                )
                                . '-'
                                . $device->id
                                . '-'
                                . Str::upper(
                                    Str::random(4)
                                ),

                            'billing_month' =>
                                $today
                                    ->copy()
                                    ->startOfMonth()
                                    ->toDateString(),

                            'amount' =>
                                $price,

                            'discount' =>
                                0,

                            'paid_amount' =>
                                $isPaid
                                    ? $price
                                    : 0,

                            'due_amount' =>
                                $isPaid
                                    ? 0
                                    : $price,

                            'issue_date' =>
                                $today
                                    ->toDateString(),

                            'due_date' =>
                                $today
                                    ->toDateString(),

                            'status' =>
                                $isPaid
                                    ? 'paid'
                                    : 'unpaid',

                            'applies_service_period' =>
                                true,

                            /*
                             * Renew All extends the
                             * service immediately,
                             * matching Quick Renew.
                             */
                            'service_applied_at' =>
                                $now,

                            'service_validity_days' =>
                                $validityDays,

                            'service_price_snapshot' =>
                                $price,

                            'service_start_date' =>
                                $baseDate
                                    ->toDateString(),

                            'service_end_date' =>
                                $newExpiry
                                    ->toDateString(),

                            'initial_due_amount' =>
                                $isPaid
                                    ? 0
                                    : $price,

                            'notes' =>
                                trim(
                                    (string) (
                                        $data['notes']
                                        ?? ''
                                    )
                                )
                                ?: 'Renew All Devices - '
                                    . $package->name,

                            'created_by' =>
                                auth()->id(),
                        ]);

                    if ($isPaid) {
                        Payment::create([
                            'invoice_id' =>
                                $invoice->id,

                            'client_id' =>
                                $device->id,

                            'amount' =>
                                $price,

                            'payment_date' =>
                                $today
                                    ->toDateString(),

                            'payment_method' =>
                                $data[
                                    'payment_method'
                                ],

                            'transaction_id' =>
                                $data[
                                    'transaction_id'
                                ]
                                ?? null,

                            'notes' =>
                                'Renew All Devices',

                            'received_by' =>
                                auth()->id(),
                        ]);
                    }

                    $device->update([
                        'expiry_date' =>
                            $newExpiry
                                ->toDateString(),
                    ]);

                    $totalAmount =
                        round(
                            $totalAmount
                            + $price,
                            2
                        );

                    $renewedIds[] =
                        $device->id;
                }

                return [
                    'device_ids' =>
                        $renewedIds,

                    'device_count' =>
                        count(
                            $renewedIds
                        ),

                    'total_amount' =>
                        $totalAmount,

                    'is_paid' =>
                        $isPaid,
                ];
            }
        );

        /*
         * Database transaction is complete.
         * Now activate every renewed device.
         */
        $routerFailures = 0;

        foreach (
            $result['device_ids']
            as $deviceId
        ) {
            try {
                $renewed =
                    Client::with([
                        'router',
                        'package',
                        'ipRange',
                    ])
                        ->findOrFail(
                            $deviceId
                        );

                $provision->unsuspend(
                    $renewed
                );
            } catch (
                Throwable $exception
            ) {
                $routerFailures++;

                Log::warning(
                    'Renew All saved but MikroTik activation failed.',
                    [
                        'client_id' =>
                            $deviceId,

                        'primary_client_id' =>
                            $primary->id,

                        'message' =>
                            $exception
                                ->getMessage(),
                    ]
                );
            }
        }

        $message =
            $result['device_count']
            . ' device(s) renewed. Total QAR '
            . number_format(
                $result['total_amount'],
                2
            )
            . (
                $result['is_paid']
                    ? ' received.'
                    : ' added as due.'
            );

        if ($routerFailures > 0) {
            $message .=
                ' '
                . $routerFailures
                . ' device(s) could not be activated on MikroTik now; the renewal/accounting records are already saved.';
        }

        return back()->with(
            'success',
            $message
        );
    }
}
