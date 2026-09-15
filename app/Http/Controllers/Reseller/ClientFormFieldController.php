<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ClientCustomField;
use App\Models\ClientCustomFieldValue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientFormFieldController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->authorizeOwner(
            $request
        );

        return Inertia::render(
            'Reseller/ClientFormFields',
            [
                'fields' =>
                    ClientCustomField::query()
                        ->withCount(
                            'values'
                        )
                        ->orderBy(
                            'sort_order'
                        )
                        ->orderBy('id')
                        ->get()
                        ->map(
                            fn (
                                ClientCustomField $field
                            ): array => [
                                'id' =>
                                    $field->id,

                                'name' =>
                                    $field->name,

                                'field_key' =>
                                    $field
                                        ->field_key,

                                'type' =>
                                    $field->type,

                                'is_required' =>
                                    (bool)
                                    $field
                                        ->is_required,

                                'is_enabled' =>
                                    (bool)
                                    $field
                                        ->is_enabled,

                                'show_in_list' =>
                                    (bool)
                                    $field
                                        ->show_in_list,

                                'show_in_reports' =>
                                    (bool)
                                    $field
                                        ->show_in_reports,

                                'show_in_invoice' =>
                                    (bool)
                                    $field
                                        ->show_in_invoice,

                                'options' =>
                                    $field
                                        ->options
                                    ?? [],

                                'values_count' =>
                                    (int)
                                    $field
                                        ->values_count,
                            ]
                        )
                        ->values(),

                'fieldTypes' => [
                    'text',
                    'number',
                    'phone',
                    'email',
                    'date',
                    'select',
                    'textarea',
                    'boolean',
                    'checkbox',
                ],
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $this->authorizeOwner(
            $request
        );

        $validated =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:120',
                ],

                'type' => [
                    'required',
                    Rule::in([
                        'text',
                        'number',
                        'phone',
                        'email',
                        'date',
                        'select',
                        'textarea',
                        'boolean',
                        'checkbox',
                    ]),
                ],

                'options' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],

                'is_required' => [
                    'required',
                    'boolean',
                ],

                'is_enabled' => [
                    'required',
                    'boolean',
                ],

                'show_in_list' => [
                    'required',
                    'boolean',
                ],

                'show_in_reports' => [
                    'required',
                    'boolean',
                ],

                'show_in_invoice' => [
                    'required',
                    'boolean',
                ],
            ]);

        $sortOrder =
            (
                (int)
                ClientCustomField::query()
                    ->max(
                        'sort_order'
                    )
            )
            + 10;

        ClientCustomField::create([
            'name' =>
                trim(
                    $validated[
                        'name'
                    ]
                ),

            'field_key' =>
                $this->uniqueKey(
                    $validated[
                        'name'
                    ]
                ),

            'type' =>
                $validated[
                    'type'
                ],

            'placeholder' =>
                null,

            'options' =>
                $this->options(
                    $validated[
                        'options'
                    ]
                    ?? null
                ),

            'is_required' =>
                (bool)
                $validated[
                    'is_required'
                ],

            'is_enabled' =>
                (bool)
                $validated[
                    'is_enabled'
                ],

            'show_in_list' =>
                (bool)
                $validated[
                    'show_in_list'
                ],

            'show_in_reports' =>
                (bool)
                $validated[
                    'show_in_reports'
                ],

            'show_in_invoice' =>
                (bool)
                $validated[
                    'show_in_invoice'
                ],

            'sort_order' =>
                $sortOrder,
        ]);

        return back()->with(
            'success',
            'Client form field added.'
        );
    }

    public function toggle(
        Request $request,
        ClientCustomField
            $clientCustomField
    ): RedirectResponse {
        $this->authorizeOwner(
            $request
        );

        $clientCustomField
            ->forceFill([
                'is_enabled' =>
                    !$clientCustomField
                        ->is_enabled,
            ])
            ->save();

        return back()->with(
            'success',
            'Client form field status updated.'
        );
    }

    public function destroy(
        Request $request,
        ClientCustomField
            $clientCustomField
    ): RedirectResponse {
        $this->authorizeOwner(
            $request
        );

        DB::transaction(
            function () use (
                $clientCustomField
            ): void {
                ClientCustomFieldValue::query()
                    ->where(
                        'custom_field_id',
                        $clientCustomField
                            ->id
                    )
                    ->delete();

                $clientCustomField
                    ->delete();
            }
        );

        return back()->with(
            'success',
            'Client form field removed.'
        );
    }

    private function authorizeOwner(
        Request $request
    ): void {
        $user =
            $request->user();

        abort_unless(
            $user
            && $user->isResellerUser()
            && (
                $user->isResellerOwner()
                || $user->hasPermission(
                    'settings.manage'
                )
            ),
            403
        );
    }

    private function uniqueKey(
        string $name
    ): string {
        $base =
            Str::slug(
                $name,
                '_'
            )
            ?: 'field';

        $candidate =
            $base;

        $counter = 2;

        while (
            ClientCustomField::withoutGlobalScopes()
                ->where(
                    'field_key',
                    $candidate
                )
                ->exists()
        ) {
            $candidate =
                $base
                . '_'
                . $counter;

            $counter++;
        }

        return $candidate;
    }

    private function options(
        ?string $value
    ): array {
        if (
            $value === null
            || trim($value) === ''
        ) {
            return [];
        }

        return collect(
            preg_split(
                '/[\r\n,]+/',
                $value
            )
            ?: []
        )
            ->map(
                fn ($item) =>
                    trim(
                        (string)
                        $item
                    )
            )
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
