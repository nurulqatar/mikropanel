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

        $routerWarning =
            false;

        try {
            $refundedClient =
                Client::with([
                    'router',
                    'package',
                    'ipRange',
                ])->findOrFail(
                    $client->id
                );

            /*
             * DB already says suspended.
             * This pushes that state to MikroTik.
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
                        $client->id,

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

        $message =
            'Refund QAR '
            . number_format(
                $result[
                    'refund_amount'
                ],
                2
            )
            . ' saved. Client is suspended and disconnected in the panel.';

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
