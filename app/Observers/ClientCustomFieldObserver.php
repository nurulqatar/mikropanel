<?php

namespace App\Observers;

use App\Models\Client;
use App\Services\ClientCustomFieldService;

class ClientCustomFieldObserver
{
    /**
     * Validated payload is kept here during
     * the current web request.
     */
    private static ?array $validatedPayload = null;

    public function saving(Client $client): void
    {
        if (!$this->isClientFormRequest()) {
            return;
        }

        $payload = request()->input(
            'custom_fields',
            []
        );

        if (!is_array($payload)) {
            $payload = [];
        }

        self::$validatedPayload = app(
            ClientCustomFieldService::class
        )->validate($payload);
    }

    public function saved(Client $client): void
    {
        if (!$this->isClientFormRequest()) {
            return;
        }

        $payload = self::$validatedPayload;

        if ($payload === null) {
            $raw = request()->input(
                'custom_fields',
                []
            );

            $payload = app(
                ClientCustomFieldService::class
            )->validate(
                is_array($raw)
                    ? $raw
                    : []
            );
        }

        app(
            ClientCustomFieldService::class
        )->sync(
            $client,
            $payload
        );
    }

    private function isClientFormRequest(): bool
    {
        if (!app()->runningInConsole()) {
            return request()->routeIs(
                'clients.store',
                'clients.update'
            );
        }

        return false;
    }
}
