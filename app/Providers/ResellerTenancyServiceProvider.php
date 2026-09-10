<?php

namespace App\Providers;

use App\Models\Client;
use App\Models\Reseller;
use App\Services\Reseller\ResellerUsageService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;

class ResellerTenancyServiceProvider extends ServiceProvider
{
    public function boot(
        ResellerUsageService $usage
    ): void {
        Client::creating(
            function (
                Client $client
            ) use ($usage): void {
                $user = Auth::user();

                /*
                 * Platform/Super Admin client
                 * creation remains unchanged.
                 */
                if (
                    !$user
                    || !$user->reseller_id
                ) {
                    return;
                }

                $reseller =
                    Reseller::query()
                        ->find(
                            $user->reseller_id
                        );

                if (!$reseller) {
                    throw ValidationException::withMessages([
                        'client' =>
                            'Reseller account was not found.',
                    ]);
                }

                if (
                    $client->reseller_id
                    && (int)
                    $client->reseller_id
                    !== (int)
                    $reseller->id
                ) {
                    throw ValidationException::withMessages([
                        'client' =>
                            'Cross-reseller client creation is not allowed.',
                    ]);
                }

                if (
                    $reseller->status
                    !== 'active'
                ) {
                    throw ValidationException::withMessages([
                        'client' =>
                            'Reseller account is not active.',
                    ]);
                }

                if (
                    !$usage
                        ->subscriptionIsUsable(
                            $reseller
                        )
                ) {
                    throw ValidationException::withMessages([
                        'client' =>
                            'Reseller subscription has expired or is unavailable.',
                    ]);
                }

                if (
                    $usage
                        ->remainingClientSlots(
                            $reseller
                        ) <= 0
                ) {
                    throw ValidationException::withMessages([
                        'client' =>
                            'Client limit reached. Upgrade or renew the reseller package.',
                    ]);
                }

                $client->reseller_id =
                    $reseller->id;
            }
        );
    }
}
