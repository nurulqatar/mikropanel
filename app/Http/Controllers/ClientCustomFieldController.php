<?php

namespace App\Http\Controllers;

use App\Models\ClientCustomField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ClientCustomFieldController extends Controller
{
    /**
     * Supported field types.
     */
    private function fieldTypes(): array
    {
        return [
            [
                'value' => 'text',
                'label' => 'Text',
            ],
            [
                'value' => 'number',
                'label' => 'Number',
            ],
            [
                'value' => 'phone',
                'label' => 'Phone / Mobile',
            ],
            [
                'value' => 'email',
                'label' => 'Email',
            ],
            [
                'value' => 'date',
                'label' => 'Date',
            ],
            [
                'value' => 'select',
                'label' => 'Dropdown / Select',
            ],
            [
                'value' => 'textarea',
                'label' => 'Long Text / Textarea',
            ],
            [
                'value' => 'boolean',
                'label' => 'Yes / No',
            ],
            [
                'value' => 'checkbox',
                'label' => 'Checkbox',
            ],
        ];
    }

    /**
     * Builder page.
     */
    public function index(): Response
    {
        $fields = ClientCustomField::query()
            ->withCount('values')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (ClientCustomField $field) {
                return [
                    'id' => $field->id,
                    'name' => $field->name,
                    'field_key' => $field->field_key,
                    'type' => $field->type,
                    'placeholder' => $field->placeholder,
                    'options' => $field->options ?? [],
                    'is_required' => (bool) $field->is_required,
                    'is_enabled' => (bool) $field->is_enabled,
                    'show_in_list' => (bool) $field->show_in_list,
                    'show_in_reports' => (bool) $field->show_in_reports,
                    'show_in_invoice' => (bool) $field->show_in_invoice,
                    'sort_order' => (int) $field->sort_order,
                    'values_count' => (int) $field->values_count,
                ];
            })
            ->values();

        $summary = [
            'total' => $fields->count(),
            'enabled' => $fields
                ->where('is_enabled', true)
                ->count(),
            'required' => $fields
                ->where('is_required', true)
                ->count(),
            'report_fields' => $fields
                ->where('show_in_reports', true)
                ->count(),
        ];

        return Inertia::render(
            'Settings/ClientFormBuilder',
            [
                'fields' => $fields,
                'fieldTypes' => $this->fieldTypes(),
                'summary' => $summary,
            ]
        );
    }

    /**
     * Create custom field.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'type' => [
                'required',
                Rule::in(
                    collect($this->fieldTypes())
                        ->pluck('value')
                        ->all()
                ),
            ],
            'placeholder' => [
                'nullable',
                'string',
                'max:255',
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
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
        ]);

        $fieldKey = $this->makeUniqueKey(
            $validated['name']
        );

        ClientCustomField::create([
            'name' => trim($validated['name']),
            'field_key' => $fieldKey,
            'type' => $validated['type'],
            'placeholder' => $this->nullableTrim(
                $validated['placeholder'] ?? null
            ),
            'options' => $this->parseOptions(
                $validated['options'] ?? null,
                $validated['type']
            ),
            'is_required' => $request->boolean(
                'is_required'
            ),
            'is_enabled' => $request->boolean(
                'is_enabled'
            ),
            'show_in_list' => $request->boolean(
                'show_in_list'
            ),
            'show_in_reports' => $request->boolean(
                'show_in_reports'
            ),
            'show_in_invoice' => $request->boolean(
                'show_in_invoice'
            ),
            'sort_order' => $validated['sort_order']
                ?? $this->nextSortOrder(),
        ]);

        return back()->with(
            'success',
            'Client custom field created successfully.'
        );
    }

    /**
     * Update custom field.
     *
     * field_key intentionally remains unchanged.
     * This protects old client data mappings.
     */
    public function update(
        Request $request,
        ClientCustomField $clientCustomField
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'type' => [
                'required',
                Rule::in(
                    collect($this->fieldTypes())
                        ->pluck('value')
                        ->all()
                ),
            ],
            'placeholder' => [
                'nullable',
                'string',
                'max:255',
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
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
        ]);

        DB::transaction(function () use (
            $request,
            $validated,
            $clientCustomField
        ) {
            $clientCustomField->update([
                'name' => trim(
                    $validated['name']
                ),
                'type' => $validated['type'],
                'placeholder' => $this->nullableTrim(
                    $validated['placeholder']
                        ?? null
                ),
                'options' => $this->parseOptions(
                    $validated['options'] ?? null,
                    $validated['type']
                ),
                'is_required' => $request->boolean(
                    'is_required'
                ),
                'is_enabled' => $request->boolean(
                    'is_enabled'
                ),
                'show_in_list' => $request->boolean(
                    'show_in_list'
                ),
                'show_in_reports' => $request->boolean(
                    'show_in_reports'
                ),
                'show_in_invoice' => $request->boolean(
                    'show_in_invoice'
                ),
                'sort_order' => $validated['sort_order']
                    ?? $clientCustomField->sort_order,
            ]);
        });

        return back()->with(
            'success',
            'Client custom field updated successfully.'
        );
    }

    /**
     * Fast switch for one field property.
     */
    public function toggle(
        Request $request,
        ClientCustomField $clientCustomField
    ): RedirectResponse {
        $validated = $request->validate([
            'property' => [
                'required',
                Rule::in([
                    'is_enabled',
                    'is_required',
                    'show_in_list',
                    'show_in_reports',
                    'show_in_invoice',
                ]),
            ],
            'value' => [
                'required',
                'boolean',
            ],
        ]);

        $clientCustomField->update([
            $validated['property'] =>
                $request->boolean('value'),
        ]);

        return back();
    }

    /**
     * Update only display order.
     */
    public function order(
        Request $request,
        ClientCustomField $clientCustomField
    ): RedirectResponse {
        $validated = $request->validate([
            'sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:9999',
            ],
        ]);

        $clientCustomField->update([
            'sort_order' => $validated['sort_order'],
        ]);

        return back();
    }

    /**
     * Safe delete behavior:
     *
     * We never delete historic client values from
     * the Form Builder. "Delete" simply disables
     * the field so old data remains recoverable.
     */
    public function destroy(
        ClientCustomField $clientCustomField
    ): RedirectResponse {
        DB::transaction(function () use (
            $clientCustomField
        ) {
            /*
             * Permanent delete.
             *
             * client_custom_field_values has
             * cascadeOnDelete(), therefore all
             * values belonging to this field are
             * permanently removed as well.
             */
            $clientCustomField->delete();
        });

        return back()->with(
            'success',
            'Custom field permanently deleted.'
        );
    }

    /**
     * Generate stable unique database key.
     */
    private function makeUniqueKey(string $name): string
    {
        $base = Str::snake(
            Str::ascii(
                Str::slug(
                    trim($name),
                    '_'
                )
            )
        );

        $base = preg_replace(
            '/[^a-z0-9_]+/',
            '',
            strtolower($base)
        );

        $base = trim(
            $base ?: 'custom_field',
            '_'
        );

        $base = substr(
            $base,
            0,
            100
        );

        $key = $base;
        $counter = 2;

        while (
            ClientCustomField::query()
                ->where('field_key', $key)
                ->exists()
        ) {
            $suffix = '_'.$counter;

            $key = substr(
                $base,
                0,
                120 - strlen($suffix)
            ).$suffix;

            $counter++;
        }

        return $key;
    }

    /**
     * Dropdown values:
     * one option per line.
     */
    private function parseOptions(
        ?string $options,
        string $type
    ): ?array {
        if ($type !== 'select') {
            return null;
        }

        $items = collect(
            preg_split(
                '/\r\n|\r|\n/',
                $options ?? ''
            )
        )
            ->map(
                fn ($item) => trim(
                    (string) $item
                )
            )
            ->filter()
            ->unique()
            ->values()
            ->all();

        return empty($items)
            ? null
            : $items;
    }

    private function nullableTrim(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }

    private function nextSortOrder(): int
    {
        return (
            (int) ClientCustomField::query()
                ->max('sort_order')
        ) + 10;
    }


    /**
     * Dynamic field definitions and optional
     * existing values for Add/Edit/Show Client.
     */
    public function data(
        \Illuminate\Http\Request $request
    ): \Illuminate\Http\JsonResponse {
        $service = app(
            \App\Services\ClientCustomFieldService::class
        );

        $values = [];

        $clientId = $request->integer(
            'client_id'
        );

        if ($clientId > 0) {
            $client = \App\Models\Client::query()
                ->findOrFail($clientId);

            $values = $service
                ->valuesForClient($client);
        }

        return response()->json([
            'fields' => $service->definitions(),
            'values' => $values,
        ]);
    }



    /**
     * Fields and values used by Client List.
     */
    public function listData(
        \Illuminate\Http\Request $request
    ): \Illuminate\Http\JsonResponse {
        $service = app(
            \App\Services\ClientCustomFieldService::class
        );

        $fields = $service->definitionsFor(
            'list'
        );

        $fieldIds = collect(
            $fields
        )
            ->pluck('id')
            ->map(
                fn ($id) => (int) $id
            )
            ->all();

        if (empty($fieldIds)) {
            return response()->json([
                'fields' => [],
                'values' => [],
            ]);
        }

        $clientIds =
            \App\Models\Client::query()
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->all();

        return response()->json([
            'fields' => $fields,
            'values' =>
                $service->valuesForClients(
                    $clientIds,
                    $fieldIds
                ),
        ]);
    }

}
