<?php

namespace App\Http\Controllers;

use App\Models\HotspotBranding;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class HotspotBrandingController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->manage($request);

        return Inertia::render(
            'Hotspot/Branding',
            [
                'branding' =>
                    HotspotBranding::current(),
            ]
        );
    }

    public function update(
        Request $request
    ): RedirectResponse {
        $this->manage($request);

        $data =
            $request->validate([
                'brand_name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'portal_title' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'support_phone' => [
                    'nullable',
                    'string',
                    'max:100',
                ],

                'support_text' => [
                    'nullable',
                    'string',
                    'max:255',
                ],

                'primary_color' => [
                    'required',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],

                'terms_text' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'show_price' => [
                    'required',
                    'boolean',
                ],

                'show_qr' => [
                    'required',
                    'boolean',
                ],
            ]);

        HotspotBranding::query()
            ->updateOrCreate(
                [
                    'id' => 1,
                ],
                array_merge(
                    $data,
                    [
                        'updated_by' =>
                            $request
                                ->user()
                                ->id,
                    ]
                )
            );

        return back()->with(
            'success',
            'Hotspot branding updated successfully.'
        );
    }

    public function portal(
        Request $request
    ): HttpResponse {
        $this->manage($request);

        return response()
            ->view(
                'hotspot.portal-login',
                [
                    'branding' =>
                        HotspotBranding::current(),
                ]
            )
            ->header(
                'Content-Type',
                'text/html; charset=UTF-8'
            )
            ->header(
                'Content-Disposition',
                'attachment; filename="login.html"'
            );
    }

    private function manage(
        Request $request
    ): void {
        abort_unless(
            $request->user()
            && $request
                ->user()
                ->hasPermission(
                    'hotspot.manage'
                ),
            403
        );
    }
}
