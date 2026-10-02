<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\HotelUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $admin =
            $this->admin();

        $hotel =
            $admin->hotel;

        return Inertia::render(
            'Hotel/Settings',
            [
                'settings' => [
                    'name' =>
                        $hotel->name,

                    'owner_name' =>
                        $hotel->owner_name,

                    'email' =>
                        $hotel->email,

                    'phone' =>
                        $hotel->phone,

                    'whatsapp' =>
                        $hotel->whatsapp,

                    'address' =>
                        $hotel->address,

                    'timezone' =>
                        $hotel->timezone,

                    'currency' =>
                        $hotel->currency,

                    'check_out_time' =>
                        substr(
                            (string)
                            $hotel->check_out_time,
                            0,
                            5
                        ),

                    'portal_title' =>
                        $hotel->portal_title,

                    'portal_subtitle' =>
                        $hotel->portal_subtitle,

                    'primary_color' =>
                        $hotel->primary_color,

                    'secondary_color' =>
                        $hotel->secondary_color,

                    'default_locale' =>
                        $hotel->default_locale,

                    'enabled_locales' =>
                        $hotel
                            ->enabled_locales
                        ?? config(
                            'hotel_portal.default_enabled_languages',
                            ['en']
                        ),

                    'portal_translations_json' =>
                        json_encode(
                            $hotel
                                ->portal_translations
                            ?? new \stdClass(),
                            JSON_PRETTY_PRINT
                            | JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                        ),

                    'terms_text' =>
                        $hotel->terms_text,

                    'privacy_text' =>
                        $hotel->privacy_text,

                    'logo_url' =>
                        $this->url(
                            $hotel->logo_path
                        ),

                    'background_url' =>
                        $this->url(
                            $hotel->background_path
                        ),
                ],
            ]
        );
    }

    public function update(
        Request $request
    ): RedirectResponse {
        $admin =
            $this->admin();

        $hotel =
            $admin->hotel;

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

                'portal_title' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'portal_subtitle' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'primary_color' => [
                    'required',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],

                'secondary_color' => [
                    'required',
                    'regex:/^#[0-9A-Fa-f]{6}$/',
                ],

                'default_locale' => [
                    'required',
                    'string',
                    Rule::in(
                        config(
                            'hotel_portal.languages',
                            ['en']
                        )
                    ),
                ],

                'enabled_locales' => [
                    'required',
                    'array',
                    'min:1',
                ],

                'enabled_locales.*' => [
                    'required',
                    'string',
                    Rule::in(
                        config(
                            'hotel_portal.languages',
                            ['en']
                        )
                    ),
                ],

                'portal_translations_json' => [
                    'nullable',
                    'string',
                    'max:200000',
                    'json',
                ],

                'terms_text' => [
                    'nullable',
                    'string',
                    'max:30000',
                ],

                'privacy_text' => [
                    'nullable',
                    'string',
                    'max:30000',
                ],

                'logo' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:4096',
                ],

                'background' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:8192',
                ],
            ]);

        $supportedLanguages =
            config(
                'hotel_portal.languages',
                ['en']
            );

        $locales =
            collect(
                $data[
                    'enabled_locales'
                ]
            )
                ->map(
                    fn ($item) =>
                        strtolower(
                            trim(
                                (string)
                                $item
                            )
                        )
                )
                ->filter(
                    fn ($item) =>
                        in_array(
                            $item,
                            $supportedLanguages,
                            true
                        )
                )
                ->unique()
                ->values();

        $portalTranslations =
            [];

        if (
            !empty(
                $data[
                    'portal_translations_json'
                ]
            )
        ) {
            $portalTranslations =
                json_decode(
                    $data[
                        'portal_translations_json'
                    ],
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );

            if (
                !is_array(
                    $portalTranslations
                )
            ) {
                $portalTranslations =
                    [];
            }
        }

        if (
            !$locales->contains(
                $data[
                    'default_locale'
                ]
            )
        ) {
            $locales->prepend(
                $data[
                    'default_locale'
                ]
            );
        }

        $hotel->fill([
            'name' =>
                $data['name'],

            'owner_name' =>
                $data[
                    'owner_name'
                ]
                ?? null,

            'email' =>
                $data['email']
                ?? null,

            'phone' =>
                $data['phone']
                ?? null,

            'whatsapp' =>
                $data['whatsapp']
                ?? null,

            'address' =>
                $data['address']
                ?? null,

            'timezone' =>
                $data['timezone'],

            'currency' =>
                strtoupper(
                    $data['currency']
                ),

            'check_out_time' =>
                $data[
                    'check_out_time'
                ],

            'portal_title' =>
                $data[
                    'portal_title'
                ],

            'portal_subtitle' =>
                $data[
                    'portal_subtitle'
                ]
                ?? null,

            'primary_color' =>
                $data[
                    'primary_color'
                ],

            'secondary_color' =>
                $data[
                    'secondary_color'
                ],

            'default_locale' =>
                $data[
                    'default_locale'
                ],

            'enabled_locales' =>
                $locales->all(),

            'portal_translations' =>
                $portalTranslations,

            'terms_text' =>
                $data[
                    'terms_text'
                ]
                ?? null,

            'privacy_text' =>
                $data[
                    'privacy_text'
                ]
                ?? null,
        ]);

        if (
            $request->hasFile(
                'logo'
            )
        ) {
            if ($hotel->logo_path) {
                Storage::disk('public')
                    ->delete(
                        $hotel->logo_path
                    );
            }

            $hotel->logo_path =
                $request
                    ->file('logo')
                    ->store(
                        'hotel-branding/'
                        . $hotel->id,
                        'public'
                    );
        }

        if (
            $request->hasFile(
                'background'
            )
        ) {
            if (
                $hotel
                    ->background_path
            ) {
                Storage::disk('public')
                    ->delete(
                        $hotel
                            ->background_path
                    );
            }

            $hotel->background_path =
                $request
                    ->file(
                        'background'
                    )
                    ->store(
                        'hotel-branding/'
                        . $hotel->id,
                        'public'
                    );
        }

        $hotel->save();

        return back()->with(
            'success',
            'Hotel settings updated.'
        );
    }

    private function admin(): HotelUser
    {
        $user =
            Auth::guard('hotel')
                ->user();

        abort_unless(
            $user
            && $user->isAdmin(),
            403
        );

        return $user;
    }

    private function url(
        ?string $path
    ): ?string {
        if (
            !$path
            || !Storage::disk('public')
                ->exists($path)
        ) {
            return null;
        }

        return Storage::disk('public')
            ->url($path);
    }
}
