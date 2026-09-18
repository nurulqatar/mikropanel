<?php

namespace App\Http\Controllers;

use App\Models\ResellerPlan;
use Inertia\Inertia;
use Inertia\Response;

class PublicWebsiteController extends Controller
{
    public function index(): Response
    {
        return Inertia::render(
            'Public/Home',
            [
                'brand' =>
                    $this->brand(),

                'plans' =>
                    $this->plans(),
            ]
        );
    }

    public function terms(): Response
    {
        return Inertia::render(
            'Public/Legal',
            [
                'brand' =>
                    $this->brand(),

                'type' =>
                    'terms',
            ]
        );
    }

    public function privacy(): Response
    {
        return Inertia::render(
            'Public/Legal',
            [
                'brand' =>
                    $this->brand(),

                'type' =>
                    'privacy',
            ]
        );
    }

    private function plans(): array
    {
        return ResellerPlan::query()
            ->where(
                'active',
                true
            )
            ->orderBy('price')
            ->orderBy(
                'client_limit'
            )
            ->get()
            ->map(
                fn (
                    ResellerPlan $plan
                ): array => [
                    'id' =>
                        $plan->id,

                    'name' =>
                        $plan->name,

                    'code' =>
                        $plan->code,

                    'client_limit' =>
                        $plan
                            ->client_limit,

                    'price' =>
                        (float)
                        $plan->price,

                    'validity_days' =>
                        $plan
                            ->validity_days,

                    'features' =>
                        collect(
                            $plan->features
                            ?? []
                        )
                            ->filter(
                                fn ($item) =>
                                    is_string(
                                        $item
                                    )
                            )
                            ->values()
                            ->all(),

                    'notes' =>
                        $plan->notes,

                    'is_free_trial' =>
                        (float)
                        $plan->price
                        <= 0.0001
                        && (int)
                        $plan
                            ->validity_days
                        === 7,
                ]
            )
            ->values()
            ->all();
    }

    private function brand(): string
    {
        $name =
            trim(
                (string)
                config(
                    'app.name',
                    ''
                )
            );

        return (
            $name === ''
            || strtolower(
                $name
            ) === 'laravel'
        )
            ? 'MikroPanel'
            : $name;
    }
}
