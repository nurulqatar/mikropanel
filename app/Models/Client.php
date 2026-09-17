<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'client_code',
        'parent_client_id',
        'device_label',
        'router_id',
        'ip_range_id',
        'package_id',

        'name',

        'mac_address',
        'active_mac_address',
        'ip_address',

        'phone',
        'email',
        'address',
        'identity_type',
        'identity_number',
        'identity_barcode',
        'nationality',
        'date_of_birth',
        'gender',
        'document_expiry_date',
        'qatar_id_number',
        'qatar_id_expiry_date',
        'occupation',
        'passport_number',
        'passport_expiry_date',
        'document_serial_number',
        'residency_type',
        'employer',
        'place_of_birth',
        'passport_issue_date',
        'issuing_country',
        'issuing_authority',
        'last_recharge_date',
        'imported_at',
        'import_batch_uuid',


        'expiry_date',
        'installed_at',
        'billing_day',

        'enabled',
        'connected',

        'mikrotik_lease_id',
        'mikrotik_arp_id',
        'mikrotik_queue_id',
    ];

    protected $hidden = [
        'qatar_id_front_image_path',
        'qatar_id_back_image_path',
        'passport_image_path',
        'profile_image_path',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'connected' => 'boolean',
        'date_of_birth' => 'date',
        'document_expiry_date' => 'date',
        'qatar_id_expiry_date' => 'date',
        'passport_expiry_date' => 'date',
        'passport_issue_date' => 'date',
        'last_recharge_date' => 'date',
        'imported_at' => 'datetime',
        'expiry_date' => 'date',
        'installed_at' => 'date',
        'billing_day' => 'integer',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        /*
         * DUPLICATE_PRIMARY_CLIENT_GUARD_V1
         *
         * One person/customer must have only one
         * primary Client account.
         *
         * Additional devices deliberately reuse
         * the primary customer's identity, so
         * parent_client_id rows are excluded.
         *
         * This model-level guard also protects
         * normal web creation, spreadsheet imports
         * and other Eloquent Client::create flows.
         */
        static::creating(
            function (Client $client): void {
                /*
                 * Linked device:
                 * same customer identity is allowed.
                 */
                if (
                    !empty(
                        $client->parent_client_id
                    )
                ) {
                    return;
                }

                /*
                 * Support both current identity fields
                 * and older Qatar ID / Passport fields.
                 */
                $identityFields = [
                    'identity_number',
                    'qatar_id_number',
                    'passport_number',
                    'identity_barcode',
                ];

                $identityValues = [];

                foreach (
                    $identityFields
                    as $field
                ) {
                    $value =
                        trim(
                            (string) (
                                $client->{$field}
                                ?? ''
                            )
                        );

                    if ($value === '') {
                        continue;
                    }

                    $identityValues[] =
                        strtoupper(
                            $value
                        );
                }

                $identityValues =
                    array_values(
                        array_unique(
                            $identityValues
                        )
                    );

                /*
                 * No QID / Passport / Barcode supplied:
                 * do not guess duplicate customer from
                 * name or phone.
                 */
                if ($identityValues === []) {
                    return;
                }

                /*
                 * Resolve the tenant explicitly.
                 *
                 * This prevents a Qatar ID belonging
                 * to reseller A from incorrectly
                 * blocking reseller B.
                 */
                $tenantId =
                    $client->reseller_id
                    ?? auth()
                        ->user()
                        ?->reseller_id;

                $duplicateQuery =
                    static::withoutGlobalScopes()
                        ->whereNull(
                            'parent_client_id'
                        );

                if ($tenantId === null) {
                    $duplicateQuery
                        ->whereNull(
                            'reseller_id'
                        );
                } else {
                    $duplicateQuery
                        ->where(
                            'reseller_id',
                            $tenantId
                        );
                }

                /*
                 * Exact normalized identity match.
                 *
                 * Cross-check all identity columns so
                 * an older client stored under the
                 * legacy Qatar ID / Passport column
                 * is also detected.
                 */
                $duplicateQuery
                    ->where(
                        function ($query) use (
                            $identityValues
                        ): void {
                            foreach (
                                [
                                    'identity_number',
                                    'qatar_id_number',
                                    'passport_number',
                                    'identity_barcode',
                                ]
                                as $column
                            ) {
                                $query->orWhereIn(
                                    \Illuminate\Support\Facades\DB::raw(
                                        'UPPER(TRIM('
                                        . $column
                                        . '))'
                                    ),
                                    $identityValues
                                );
                            }
                        }
                    );

                $existing =
                    $duplicateQuery
                        ->first([
                            'id',
                            'client_code',
                            'name',
                            'deleted_at',
                        ]);

                if (!$existing) {
                    return;
                }

                $existingLabel =
                    $existing->client_code
                    ?: '#'
                        . $existing->id;

                $message =
                    'Client already exists: '
                    . $existingLabel
                    . ' · '
                    . $existing->name
                    . (
                        $existing->trashed()
                            ? ' (Archived). '
                            : '. '
                    )
                    . 'Open the existing client and use ADD DEVICE instead of creating a new client.';

                $errors = [];

                foreach (
                    $identityFields
                    as $field
                ) {
                    if (
                        trim(
                            (string) (
                                $client->{$field}
                                ?? ''
                            )
                        ) !== ''
                    ) {
                        $errors[$field] =
                            $message;
                    }
                }

                if ($errors === []) {
                    $errors[
                        'identity_number'
                    ] = $message;
                }

                throw
                    \Illuminate\Validation\ValidationException::withMessages(
                        $errors
                    );
            }
        );

        static::saved(
            function (Client $client): void {
                if (
                    app()->runningInConsole()
                    || !app()->bound('request')
                ) {
                    return;
                }

                $request =
                    request();

                $tokens = [
                    'qatar_id_front' =>
                        $request->input(
                            'qatar_id_front_scan_token'
                        ),

                    'qatar_id_back' =>
                        $request->input(
                            'qatar_id_back_scan_token'
                        ),

                    'passport' =>
                        $request->input(
                            'passport_scan_token'
                        ),
                ];

                $hasToken = false;

                foreach (
                    $tokens
                    as $token
                ) {
                    if (
                        is_string(
                            $token
                        )
                        && trim(
                            $token
                        ) !== ''
                    ) {
                        $hasToken = true;
                        break;
                    }
                }

                if (!$hasToken) {
                    return;
                }

                $finalize =
                    static function () use (
                        $client,
                        $tokens
                    ): void {
                        try {
                            app(
                                \App\Services\ClientIdentityImageService::class
                            )->finalizeTokens(
                                $client,
                                $tokens
                            );
                        } catch (
                            \Throwable $exception
                        ) {
                            \Illuminate\Support\Facades\Log::error(
                                'Client identity image finalization failed.',
                                [
                                    'client_id' =>
                                        $client->id,

                                    'message' =>
                                        $exception
                                            ->getMessage(),
                                ]
                            );
                        }
                    };

                if (
                    \Illuminate\Support\Facades\DB::transactionLevel()
                    > 0
                ) {
                    \Illuminate\Support\Facades\DB::afterCommit(
                        $finalize
                    );

                    return;
                }

                $finalize();
            }
        );
    }

    public function primaryClient()
    {
        return $this->belongsTo(
            self::class,
            'parent_client_id'
        )->withTrashed();
    }

    public function devices(): HasMany
    {
        return $this->hasMany(
            self::class,
            'parent_client_id'
        );
    }

    public function router()
    {
        return $this->belongsTo(
            Router::class
        );
    }

    public function package()
    {
        return $this->belongsTo(
            Package::class
        );
    }

    public function ipRange()
    {
        return $this->belongsTo(
            IpRange::class
        );
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(
            Invoice::class
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            Payment::class
        );
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(
            ClientRefund::class
        );
    }

    public function monthlyUsages(): HasMany
    {
        return $this->hasMany(
            ClientMonthlyUsage::class
        );
    }

    public function routerBindings(): HasMany
    {
        return $this->hasMany(
            ClientRouterBinding::class
        );
    }


    public function customFieldValues()
    {
        return $this->hasMany(
            \App\Models\ClientCustomFieldValue::class,
            'client_id'
        );
    }

    public function customFields()
    {
        return $this->belongsToMany(
            \App\Models\ClientCustomField::class,
            'client_custom_field_values',
            'client_id',
            'custom_field_id'
        )
        ->withPivot('value')
        ->withTimestamps();
    }

}
