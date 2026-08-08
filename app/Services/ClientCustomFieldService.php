<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientCustomField;
use App\Models\ClientCustomFieldValue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ClientCustomFieldService
{
    /**
     * All enabled fields used in Add/Edit Client.
     */
    public function enabledFields(): Collection
    {
        return ClientCustomField::query()
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Fields for one presentation context.
     *
     * form    = all enabled
     * list    = show_in_list
     * report  = show_in_reports
     * invoice = show_in_invoice
     */
    public function fieldsForContext(
        string $context = 'form'
    ): Collection {
        $query = ClientCustomField::query()
            ->where('is_enabled', true);

        switch ($context) {
            case 'list':
                $query->where(
                    'show_in_list',
                    true
                );
                break;

            case 'report':
                $query->where(
                    'show_in_reports',
                    true
                );
                break;

            case 'invoice':
                $query->where(
                    'show_in_invoice',
                    true
                );
                break;
        }

        return $query
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Default Add/Edit definitions.
     */
    public function definitions(): array
    {
        return $this->definitionsFor(
            'form'
        );
    }

    /**
     * Definitions for List/Report/Invoice/Form.
     */
    public function definitionsFor(
        string $context
    ): array {
        return $this->fieldsForContext(
            $context
        )
            ->map(
                fn (
                    ClientCustomField $field
                ) => $this->definition(
                    $field
                )
            )
            ->values()
            ->all();
    }

    private function definition(
        ClientCustomField $field
    ): array {
        return [
            'id' => $field->id,
            'name' => $field->name,
            'field_key' =>
                $field->field_key,
            'type' => $field->type,
            'placeholder' =>
                $field->placeholder,
            'options' =>
                $field->options ?? [],
            'is_required' =>
                (bool) $field->is_required,
            'is_enabled' =>
                (bool) $field->is_enabled,
            'show_in_list' =>
                (bool) $field->show_in_list,
            'show_in_reports' =>
                (bool) $field->show_in_reports,
            'show_in_invoice' =>
                (bool) $field->show_in_invoice,
            'sort_order' =>
                (int) $field->sort_order,
        ];
    }

    /**
     * Existing values for one client.
     */
    public function valuesForClient(
        Client $client
    ): array {
        return ClientCustomFieldValue::query()
            ->where(
                'client_id',
                $client->id
            )
            ->get()
            ->mapWithKeys(
                function (
                    ClientCustomFieldValue $value
                ) {
                    return [
                        (string)
                        $value->custom_field_id
                            => $value->value,
                    ];
                }
            )
            ->all();
    }

    /**
     * Values for many clients.
     *
     * Result:
     * [
     *   client_id => [
     *      field_id => value
     *   ]
     * ]
     */
    public function valuesForClients(
        array $clientIds,
        array $fieldIds = []
    ): array {
        $clientIds = collect(
            $clientIds
        )
            ->filter()
            ->map(
                fn ($id) => (int) $id
            )
            ->unique()
            ->values()
            ->all();

        if (empty($clientIds)) {
            return [];
        }

        $query =
            ClientCustomFieldValue::query()
                ->whereIn(
                    'client_id',
                    $clientIds
                );

        if (!empty($fieldIds)) {
            $fieldIds = collect(
                $fieldIds
            )
                ->filter()
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->unique()
                ->values()
                ->all();

            $query->whereIn(
                'custom_field_id',
                $fieldIds
            );
        }

        return $query
            ->get()
            ->groupBy('client_id')
            ->map(
                function (
                    Collection $rows
                ) {
                    return $rows
                        ->mapWithKeys(
                            fn (
                                ClientCustomFieldValue $row
                            ) => [
                                (string)
                                $row->custom_field_id
                                    => $row->value,
                            ]
                        )
                        ->all();
                }
            )
            ->mapWithKeys(
                fn (
                    $values,
                    $clientId
                ) => [
                    (string) $clientId
                        => $values,
                ]
            )
            ->all();
    }

    /**
     * Rows ready for printed documents.
     */
    public function rowsForClient(
        Client $client,
        string $context
    ): array {
        $fields =
            $this->fieldsForContext(
                $context
            );

        if ($fields->isEmpty()) {
            return [];
        }

        $values =
            ClientCustomFieldValue::query()
                ->where(
                    'client_id',
                    $client->id
                )
                ->whereIn(
                    'custom_field_id',
                    $fields->pluck('id')
                )
                ->get()
                ->keyBy(
                    'custom_field_id'
                );

        return $fields
            ->map(
                function (
                    ClientCustomField $field
                ) use ($values) {
                    $raw =
                        $values->get(
                            $field->id
                        )?->value;

                    return [
                        'id' =>
                            $field->id,
                        'name' =>
                            $field->name,
                        'field_key' =>
                            $field->field_key,
                        'type' =>
                            $field->type,
                        'value' =>
                            $raw,
                        'display_value' =>
                            $this->displayValue(
                                $field,
                                $raw
                            ),
                    ];
                }
            )
            ->values()
            ->all();
    }

    /**
     * Human-readable output.
     */
    public function displayValue(
        ClientCustomField $field,
        mixed $value
    ): string {
        if (
            $value === null
            || $value === ''
        ) {
            return '-';
        }

        if (
            $field->type === 'boolean'
            || $field->type === 'checkbox'
        ) {
            return in_array(
                (string) $value,
                ['1', 'true', 'yes'],
                true
            )
                ? 'Yes'
                : 'No';
        }

        return (string) $value;
    }

    /**
     * Validate Add/Edit Client values.
     */
    public function validate(
        array $payload
    ): array {
        $fields =
            $this->enabledFields();

        if ($fields->isEmpty()) {
            return [];
        }

        $rules = [];

        foreach ($fields as $field) {
            $rules[
                'custom_fields.'.
                $field->id
            ] = $this->rulesForField(
                $field
            );
        }

        $validator =
            Validator::make(
                [
                    'custom_fields'
                        => $payload,
                ],
                $rules,
                [],
                $this->attributeNames(
                    $fields
                )
            );

        if ($validator->fails()) {
            throw new ValidationException(
                $validator
            );
        }

        $validated = [];

        foreach ($fields as $field) {
            $id = (string)
                $field->id;

            if (
                !array_key_exists(
                    $id,
                    $payload
                )
                && !array_key_exists(
                    $field->id,
                    $payload
                )
            ) {
                continue;
            }

            $value =
                $payload[$id]
                ?? $payload[
                    $field->id
                ]
                ?? null;

            $validated[$id] =
                $this->normalizeValue(
                    $field,
                    $value
                );
        }

        return $validated;
    }

    /**
     * Save enabled field values.
     *
     * Disabled historical fields are
     * intentionally untouched.
     */
    public function sync(
        Client $client,
        array $payload
    ): void {
        $fields =
            $this->enabledFields()
                ->keyBy(
                    fn ($field) =>
                        (string)
                        $field->id
                );

        foreach (
            $payload
            as $fieldId => $value
        ) {
            $key = (string)
                $fieldId;

            if (!$fields->has($key)) {
                continue;
            }

            ClientCustomFieldValue::query()
                ->updateOrCreate(
                    [
                        'client_id' =>
                            $client->id,
                        'custom_field_id' =>
                            (int) $fieldId,
                    ],
                    [
                        'value' =>
                            $this->storageValue(
                                $fields->get(
                                    $key
                                ),
                                $value
                            ),
                    ]
                );
        }
    }

    private function rulesForField(
        ClientCustomField $field
    ): array {
        $rules = [];

        $rules[] =
            $field->is_required
                ? 'required'
                : 'nullable';

        switch ($field->type) {
            case 'number':
                $rules[] = 'numeric';
                break;

            case 'email':
                $rules[] = 'email';
                $rules[] = 'max:255';
                break;

            case 'date':
                $rules[] = 'date';
                break;

            case 'phone':
                $rules[] = 'string';
                $rules[] = 'max:50';
                break;

            case 'select':
                $options =
                    is_array(
                        $field->options
                    )
                        ? $field->options
                        : [];

                if (!empty($options)) {
                    $rules[] =
                        Rule::in(
                            $options
                        );
                }
                break;

            case 'boolean':
            case 'checkbox':
                $rules[] = 'boolean';
                break;

            case 'textarea':
                $rules[] = 'string';
                $rules[] = 'max:10000';
                break;

            case 'text':
            default:
                $rules[] = 'string';
                $rules[] = 'max:5000';
                break;
        }

        return $rules;
    }

    private function attributeNames(
        Collection $fields
    ): array {
        $attributes = [];

        foreach ($fields as $field) {
            $attributes[
                'custom_fields.'.
                $field->id
            ] = $field->name;
        }

        return $attributes;
    }

    private function normalizeValue(
        ClientCustomField $field,
        mixed $value
    ): mixed {
        if ($value === null) {
            return null;
        }

        if (
            $field->type === 'boolean'
            || $field->type === 'checkbox'
        ) {
            return filter_var(
                $value,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            ) ?? false;
        }

        if (is_string($value)) {
            $value = trim(
                $value
            );

            return $value === ''
                ? null
                : $value;
        }

        return $value;
    }

    private function storageValue(
        ClientCustomField $field,
        mixed $value
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        if (
            $field->type === 'boolean'
            || $field->type === 'checkbox'
        ) {
            return filter_var(
                $value,
                FILTER_VALIDATE_BOOLEAN
            )
                ? '1'
                : '0';
        }

        return (string) $value;
    }
}
