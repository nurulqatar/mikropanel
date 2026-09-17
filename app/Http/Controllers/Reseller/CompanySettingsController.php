<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $owner =
            $this->owner(
                $request
            );

        $reseller =
            Reseller::query()
                ->findOrFail(
                    $owner->reseller_id
                );

        return Inertia::render(
            'Reseller/CompanySettings',
            [
                'company' => [
                    'company_name' =>
                        $reseller->company_name,

                    'owner_name' =>
                        $reseller->owner_name,

                    'email' =>
                        $reseller->email,

                    'phone' =>
                        $reseller->phone,

                    'address' =>
                        $reseller->address,

                    'timezone' =>
                        $reseller->timezone
                        ?: 'Asia/Qatar',

                    'currency' =>
                        $reseller->currency
                        ?: 'QAR',
                ],
            ]
        );
    }

    public function update(
        Request $request
    ): RedirectResponse {
        $owner =
            $this->owner(
                $request
            );

        $data =
            $request->validate([
                'company_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'owner_name' => [
                    'required',
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
                    'max:60',
                ],

                'address' => [
                    'nullable',
                    'string',
                    'max:1000',
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
            ]);

        $reseller =
            Reseller::query()
                ->findOrFail(
                    $owner->reseller_id
                );

        $reseller->fill([
            'company_name' =>
                trim(
                    $data['company_name']
                ),

            'owner_name' =>
                trim(
                    $data['owner_name']
                ),

            'email' =>
                $data['email']
                    ? strtolower(
                        trim(
                            $data['email']
                        )
                    )
                    : null,

            'phone' =>
                $data['phone']
                    ? trim(
                        $data['phone']
                    )
                    : null,

            'address' =>
                $data['address']
                    ? trim(
                        $data['address']
                    )
                    : null,

            'timezone' =>
                $data['timezone'],

            'currency' =>
                strtoupper(
                    trim(
                        $data['currency']
                    )
                ),
        ]);

        $reseller->save();

        return back()->with(
            'success',
            'Company settings updated.'
        );
    }

    private function owner(
        Request $request
    ): User {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->reseller_id
            && $user
                ->isResellerOwner(),
            403
        );

        return $user;
    }
}
