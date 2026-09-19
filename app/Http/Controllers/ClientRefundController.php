<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Services\ClientProvisionService;
use App\Services\ClientRefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClientRefundController extends Controller
{
    public function preview(
        Client $client,
        ClientRefundService $refunds
    ): JsonResponse {
        return response()->json(
            $refunds->preview(
                $client
            )
        );
    }

    public function store(
        Request $request,
        Client $client,
        ClientRefundService $refunds,
        ClientProvisionService $provision
    ): RedirectResponse {
        $data =
            $request->validate([
                'invoice_id' => [
                    'required',
                    'integer',
                ],

                'reason' => [
                    'required',
                    'string',
                    'max:1000',
                ],
            ]);

        $result =
            $refunds->refund(
                $client,
                (int)
                $data['invoice_id'],
                trim(
                    $data['reason']
                )
            );

        /*
         * ACCOUNT_DUE_FAMILY_SUSPEND_V1
         *
         * Normal refund => one device.
         * Due-mode refund => every device in the account.
         *
         * Each router sync is attempted independently so
         * one unreachable router cannot prevent the other
         * devices from being suspended.
         */
        $routerWarning =
            false;

        $suspendClientIds =
            collect(
                $result[
                    'suspend_client_ids'
                ]
                ?? [
                    $client->id,
                ]
            )
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->unique()
                ->values();

        foreach (
            $suspendClientIds
            as $suspendClientId
        ) {
            try {
                $refundedClient =
                    Client::withoutGlobalScopes()
                        ->with([
                            'router',
                            'package',
                            'ipRange',
                        ])
                        ->findOrFail(
                            $suspendClientId
                        );

                /*
                 * DB is already suspended by the
                 * transaction. Push that state to
                 * MikroTik now.
                 */
                $provision->suspend(
                    $refundedClient
                );
            } catch (
                Throwable $exception
            ) {
                $routerWarning =
                    true;

                Log::warning(
                    'Refund saved but MikroTik suspension could not be confirmed.',
                    [
                        'client_id' =>
                            $suspendClientId,

                        'batch_uuid' =>
                            $result[
                                'batch_uuid'
                            ],

                        'message' =>
                            $exception
                                ->getMessage(),
                    ]
                );
            }
        }

        if (
            $result[
                'account_due_mode'
            ]
            ?? false
        ) {
            $message =
                'Refund QAR '
                . number_format(
                    (float)
                    $result[
                        'refund_amount'
                    ],
                    2
                )
                . ' saved after deducting QAR '
                . number_format(
                    (float) (
                        $result[
                            'due_usage_offset'
                        ]
                        ?? 0
                    ),
                    2
                )
                . ' unpaid used service from due device(s). '
                . (
                    (int) (
                        $result[
                            'family_device_count'
                        ]
                        ?? 1
                    )
                )
                . ' devices are suspended. '
                . 'Unused future due service was cancelled.';
        } else {
            $message =
                'Refund QAR '
                . number_format(
                    $result[
                        'refund_amount'
                    ],
                    2
                )
                . ' saved. Client is suspended and disconnected in the panel.';
        }

        if (
            $routerWarning
        ) {
            $message .=
                ' MikroTik is unavailable, so physical router disconnection will complete when the router is reachable/synced.';
        }

        return back()->with(
            'success',
            $message
        );
    }
}
