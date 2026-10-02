<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerPlan;
use App\Services\Reseller\ResellerPlanBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function upgrade(
        Request $request,
        ResellerPlan $plan,
        ResellerPlanBillingService $billing
    ): RedirectResponse {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->isResellerOwner(),
            403,
            'Only the reseller owner can upgrade the package.'
        );

        $reseller =
            Reseller::query()
                ->findOrFail(
                    $user->reseller_id
                );

        $subscription =
            $billing->upgrade(
                $reseller,
                $plan,
                (int)
                $user->id
            );

        return back()->with(
            'success',
            'Package upgraded successfully. New expiry: '
                . (
                    $subscription
                        ->expires_at
                        ?->timezone(
                            $reseller->timezone
                            ?: 'Asia/Qatar'
                        )
                        ->format(
                            'Y-m-d H:i'
                        )
                    ?? '-'
                )
                . '.'
        );
    }
}
