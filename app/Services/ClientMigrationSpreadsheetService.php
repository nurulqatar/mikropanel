<?php

namespace App\Services;

use App\Models\Client;
use App\Models\ClientCustomField;
use App\Models\IpRange;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

class ClientMigrationSpreadsheetService
{
    public function __construct(
        private ClientCustomFieldService
            $customFields,

        private IpAllocatorService
            $allocator
    ) {
    }

    public function template(): string
    {
        $fields =
            $this->customFields
                ->enabledFields();

        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet
                ->getActiveSheet();

        $sheet->setTitle(
            'Clients'
        );

        $headers =
            $this->headers(
                $fields
            );

        $sheet->fromArray(
            $headers,
            null,
            'A1'
        );

        $sheet
            ->getStyle(
                'A1:'
                . $sheet
                    ->getHighestColumn()
                . '1'
            )
            ->getFont()
            ->setBold(true);

        $sheet->freezePane(
            'A2'
        );

        foreach (
            [
                'A',
                'B',
                'C',
                'J',
                'K',
                'L',
            ]
            as $column
        ) {
            $sheet
                ->getStyle(
                    $column
                    . ':'
                    . $column
                )
                ->getNumberFormat()
                ->setFormatCode('@');
        }

        $instructions =
            $spreadsheet
                ->createSheet();

        $instructions->setTitle(
            'Instructions'
        );

        $instructions->fromArray([
            [
                'MikroPanel Legacy Client Import',
                '',
            ],
            [
                'Mandatory',
                'Client Name, Mobile Number, MAC Address',
            ],
            [
                'Package',
                'Optional in Excel. Blank uses the Default Package selected before import.',
            ],
            [
                'IP Pool',
                'Optional in Excel. Blank uses the Default IP Pool selected before import.',
            ],
            [
                'Recharge Date',
                'Optional. If Expiry Date is blank, expiry is calculated using package validity.',
            ],
            [
                'Example',
                '02 Sep 2026 + 30-day package = 02 Oct 2026.',
            ],
            [
                'Expiry Date',
                'Optional. If entered, that exact date becomes the first disconnected/expired date.',
            ],
            [
                'Opening money',
                'Imported legacy clients create NO invoice, NO payment and NO collection for their current service period.',
            ],
            [
                'IP address',
                'Do not enter an IP. MikroPanel automatically allocates the first free IP.',
            ],
            [
                'Custom fields',
                'Every enabled Client Form Builder field appears automatically. During legacy import these fields are optional even when the normal Add Client form marks them required.',
            ],
            [
                'Blank optional fields',
                'Blank values are not stored. They may be added later through Edit Client.',
            ],
            [
                'MikroTik',
                'Active imported clients are automatically picked up by the existing clients:sync-routers scheduler.',
            ],
        ]);

        $instructions
            ->getColumnDimension('A')
            ->setWidth(24);

        $instructions
            ->getColumnDimension('B')
            ->setWidth(100);

        return $this->save(
            $spreadsheet,
            'MikroPanel_Client_Import_Template'
        );
    }

    public function export(): string
    {
        $fields =
            $this->customFields
                ->enabledFields();

        $spreadsheet =
            new Spreadsheet();

        $sheet =
            $spreadsheet
                ->getActiveSheet();

        $sheet->setTitle(
            'Clients'
        );

        $headers =
            array_merge(
                $this->headers(
                    $fields
                ),
                [
                    'Client Code',
                    'Allocated IP',
                    'Status',
                ]
            );

        $sheet->fromArray(
            $headers,
            null,
            'A1'
        );

        $sheet
            ->getStyle(
                'A1:'
                . $sheet
                    ->getHighestColumn()
                . '1'
            )
            ->getFont()
            ->setBold(true);

        $sheet->freezePane(
            'A2'
        );

        $rowNumber = 2;

        Client::query()
            ->with([
                'package:id,name',
                'ipRange:id,name',
            ])
            ->orderBy('id')
            ->chunkById(
                200,
                function (
                    Collection $clients
                ) use (
                    $fields,
                    $sheet,
                    &$rowNumber
                ): void {
                    $customValues =
                        $this
                            ->customFields
                            ->valuesForClients(
                                $clients
                                    ->pluck('id')
                                    ->all(),
                                $fields
                                    ->pluck('id')
                                    ->all()
                            );

                    foreach (
                        $clients
                        as $client
                    ) {
                        $values =
                            $customValues[
                                (string)
                                $client->id
                            ]
                            ?? [];

                        $row = [
                            $client->name,
                            $client->phone,
                            $client
                                ->active_mac_address
                                ?: $client
                                    ->mac_address,

                            $client
                                ->package
                                ?->name,

                            $client
                                ->ipRange
                                ?->name,

                            $client
                                ->last_recharge_date
                                ?->format(
                                    'Y-m-d'
                                ),

                            $client
                                ->expiry_date
                                ?->format(
                                    'Y-m-d'
                                ),

                            $client->email,
                            $client->address,
                            $client->identity_type,
                            $client->identity_number,
                            $client->identity_barcode,
                            $client->nationality,

                            $client
                                ->date_of_birth
                                ?->format(
                                    'Y-m-d'
                                ),

                            $client->gender,

                            $client
                                ->document_expiry_date
                                ?->format(
                                    'Y-m-d'
                                ),

                            $client
                                ->installed_at
                                ?->format(
                                    'Y-m-d'
                                ),
                        ];

                        foreach (
                            $fields
                            as $field
                        ) {
                            $row[] =
                                $values[
                                    (string)
                                    $field->id
                                ]
                                ?? null;
                        }

                        $row[] =
                            $client
                                ->client_code;

                        $row[] =
                            $client
                                ->ip_address;

                        $row[] =
                            $client
                                ->enabled
                                ? 'ACTIVE'
                                : 'SUSPENDED';

                        $sheet->fromArray(
                            $row,
                            null,
                            'A'
                            . $rowNumber
                        );

                        $rowNumber++;
                    }
                }
            );

        return $this->save(
            $spreadsheet,
            'MikroPanel_Clients_Export'
        );
    }

    public function import(
        UploadedFile $file,
        int $defaultPackageId,
        int $defaultIpRangeId
    ): array {
        $defaultPackage =
            Package::query()
                ->where(
                    'enabled',
                    true
                )
                ->findOrFail(
                    $defaultPackageId
                );

        $defaultRange =
            IpRange::query()
                ->where(
                    'enabled',
                    true
                )
                ->findOrFail(
                    $defaultIpRangeId
                );

        $reader =
            IOFactory::createReaderForFile(
                $file->getRealPath()
            );

        $reader->setReadDataOnly(
            true
        );

        $spreadsheet =
            $reader->load(
                $file->getRealPath()
            );

        $sheet =
            $spreadsheet
                ->getSheet(0);

        $rows =
            $sheet->toArray(
                null,
                true,
                false,
                false
            );

        $spreadsheet
            ->disconnectWorksheets();

        if (count($rows) < 2) {
            throw new RuntimeException(
                'The Excel file contains no client rows.'
            );
        }

        if (count($rows) > 3001) {
            throw new RuntimeException(
                'Maximum 3000 client rows are allowed per import file.'
            );
        }

        $headers =
            array_map(
                fn ($value): string =>
                    trim(
                        (string)
                        $value
                    ),
                array_shift($rows)
            );

        $headerKeys =
            collect($headers)
                ->map(
                    fn (
                        string $header
                    ): string =>
                        $this
                            ->normalizeHeader(
                                $header
                            )
                )
                ->filter()
                ->values()
                ->all();

        foreach (
            [
                'client name',
                'mobile number',
                'mac address',
            ]
            as $mandatory
        ) {
            if (
                !in_array(
                    $mandatory,
                    $headerKeys,
                    true
                )
            ) {
                throw new RuntimeException(
                    'Mandatory Excel column missing: '
                    . $mandatory
                );
            }
        }

        $fields =
            $this->customFields
                ->enabledFields();

        $batch =
            (string)
            Str::uuid();

        $success = 0;
        $failed = 0;
        $active = 0;
        $suspended = 0;
        $errors = [];

        foreach (
            $rows
            as $offset => $values
        ) {
            $excelRow =
                $offset + 2;

            $row = [];

            foreach (
                $headers
                as $index => $header
            ) {
                if (
                    trim($header)
                    === ''
                ) {
                    continue;
                }

                $row[$header] =
                    $values[
                        $index
                    ]
                    ?? null;
            }

            if (
                collect($row)
                    ->filter(
                        fn ($value): bool =>
                            $value !== null
                            && trim(
                                (string)
                                $value
                            ) !== ''
                    )
                    ->isEmpty()
            ) {
                continue;
            }

            try {
                $client =
                    $this->importRow(
                        $row,
                        $fields,
                        $defaultPackage,
                        $defaultRange,
                        $batch
                    );

                $success++;

                if (
                    $client->enabled
                ) {
                    $active++;
                } else {
                    $suspended++;
                }
            } catch (Throwable $exception) {
                $failed++;

                if (
                    count($errors) < 100
                ) {
                    $errors[] =
                        'Row '
                        . $excelRow
                        . ': '
                        . $exception
                            ->getMessage();
                }
            }
        }

        return [
            'batch_uuid' =>
                $batch,

            'success' =>
                $success,

            'failed' =>
                $failed,

            'active' =>
                $active,

            'suspended' =>
                $suspended,

            'errors' =>
                $errors,

            'opening_invoices_created' =>
                0,

            'opening_payments_created' =>
                0,

            'opening_collection' =>
                0,

            'mikrotik_sync' =>
                'Automatic clients:sync-routers scheduler',
        ];
    }

    public function deriveExpiry(
        Carbon $rechargeDate,
        int $validityDays
    ): Carbon {
        /*
         * Existing package convention:
         * a 30-day monthly service begun on
         * Sep 02 expires on Oct 02.
         */
        if ($validityDays === 30) {
            return $rechargeDate
                ->copy()
                ->addMonthNoOverflow();
        }

        return $rechargeDate
            ->copy()
            ->addDays(
                max(
                    1,
                    $validityDays
                )
            );
    }

    private function importRow(
        array $row,
        Collection $fields,
        Package $defaultPackage,
        IpRange $defaultRange,
        string $batch
    ): Client {
        $name =
            trim(
                (string)
                $this->rowValue(
                    $row,
                    [
                        'client name',
                        'name',
                    ]
                )
            );

        $phone =
            trim(
                (string)
                $this->rowValue(
                    $row,
                    [
                        'mobile number',
                        'mobile',
                        'phone',
                    ]
                )
            );

        $mac =
            $this->normalizeMac(
                (string)
                $this->rowValue(
                    $row,
                    [
                        'mac address',
                        'mac',
                    ]
                )
            );

        if ($name === '') {
            throw new RuntimeException(
                'Client Name is required.'
            );
        }

        if ($phone === '') {
            throw new RuntimeException(
                'Mobile Number is required.'
            );
        }

        if (!$mac) {
            throw new RuntimeException(
                'A valid MAC Address is required.'
            );
        }

        $duplicateMac =
            Client::query()
                ->where(
                    function (
                        $query
                    ) use ($mac): void {
                        $query
                            ->where(
                                'mac_address',
                                $mac
                            )
                            ->orWhere(
                                'active_mac_address',
                                $mac
                            );
                    }
                )
                ->exists();

        if ($duplicateMac) {
            throw new RuntimeException(
                'MAC already exists: '
                . $mac
            );
        }

        $package =
            $this->resolvePackage(
                $row,
                $defaultPackage
            );

        $range =
            $this->resolveRange(
                $row,
                $defaultRange
            );

        if (
            !$range->router_id
        ) {
            throw new RuntimeException(
                'The selected IP Pool has no router.'
            );
        }

        $ip =
            $this->allocator
                ->allocate(
                    $range
                );

        if (!$ip) {
            throw new RuntimeException(
                'No free IP is available in '
                . $range->name
                . '.'
            );
        }

        $rechargeDate =
            $this->parseDate(
                $this->rowValue(
                    $row,
                    [
                        'recharge date',
                        'last recharge date',
                    ]
                )
            );

        $expiryDate =
            $this->parseDate(
                $this->rowValue(
                    $row,
                    [
                        'expiry date',
                        'expire date',
                    ]
                )
            );

        if (
            !$expiryDate
            && $rechargeDate
        ) {
            $expiryDate =
                $this->deriveExpiry(
                    $rechargeDate,
                    (int)
                    $package
                        ->validity_days
                );
        }

        $today =
            Carbon::today(
                'Asia/Qatar'
            );

        /*
         * Same-day expiry:
         * expiry=Oct 02 means Oct 02 is already
         * expired and must not be imported active.
         */
        $enabled =
            $expiryDate
                ? $expiryDate->gt(
                    $today
                )
                : false;

        $installedAt =
            $this->parseDate(
                $this->rowValue(
                    $row,
                    [
                        'installed date',
                        'installation date',
                    ]
                )
            )
            ?: $rechargeDate
            ?: $today;

        $identityNumber =
            $this->nullableString(
                $this->rowValue(
                    $row,
                    [
                        'identity number',
                        'qatar id',
                        'qid',
                        'passport number',
                    ]
                )
            );

        $identityType =
            strtolower(
                (string)
                $this->nullableString(
                    $this->rowValue(
                        $row,
                        [
                            'identity type',
                            'id type',
                        ]
                    )
                )
            );

        if (
            !in_array(
                $identityType,
                [
                    'qatar_id',
                    'passport',
                    'other',
                ],
                true
            )
        ) {
            $identityType = '';
        }

        if (
            $identityType === ''
            && $identityNumber
            && preg_match(
                '/^\d{11}$/',
                $identityNumber
            )
        ) {
            $identityType =
                'qatar_id';
        }

        if (
            $identityType === ''
            && $identityNumber
        ) {
            $identityType =
                'passport';
        }

        $customPayload =
            $this->customPayload(
                $row,
                $fields
            );

        return DB::transaction(
            function () use (
                $name,
                $phone,
                $mac,
                $package,
                $range,
                $ip,
                $rechargeDate,
                $expiryDate,
                $today,
                $installedAt,
                $enabled,
                $identityType,
                $identityNumber,
                $batch,
                $row,
                $customPayload
            ): Client {
                $client =
                    Client::create([
                        /*
                         * Temporary unique code.
                         * It is replaced with the normal
                         * CLI-00001 format after insert.
                         */
                        'client_code' =>
                            'IMP-'
                            . Str::upper(
                                Str::random(
                                    12
                                )
                            ),

                        'router_id' =>
                            $range
                                ->router_id,

                        'ip_range_id' =>
                            $range->id,

                        'package_id' =>
                            $package->id,

                        'name' =>
                            $name,

                        'phone' =>
                            $phone,

                        'mac_address' =>
                            $mac,

                        'active_mac_address' =>
                            $mac,

                        'ip_address' =>
                            $ip,

                        'email' =>
                            $this
                                ->nullableString(
                                    $this
                                        ->rowValue(
                                            $row,
                                            [
                                                'email',
                                            ]
                                        )
                                ),

                        'address' =>
                            $this
                                ->nullableString(
                                    $this
                                        ->rowValue(
                                            $row,
                                            [
                                                'address',
                                            ]
                                        )
                                ),

                        'identity_type' =>
                            $identityType
                                ?: null,

                        'identity_number' =>
                            $identityNumber,

                        'identity_barcode' =>
                            $this
                                ->nullableString(
                                    $this
                                        ->rowValue(
                                            $row,
                                            [
                                                'identity barcode',
                                                'barcode',
                                            ]
                                        )
                                ),

                        'nationality' =>
                            $this
                                ->nullableString(
                                    $this
                                        ->rowValue(
                                            $row,
                                            [
                                                'nationality',
                                            ]
                                        )
                                ),

                        'date_of_birth' =>
                            $this
                                ->dateString(
                                    $this
                                        ->parseDate(
                                            $this
                                                ->rowValue(
                                                    $row,
                                                    [
                                                        'date of birth',
                                                        'dob',
                                                    ]
                                                )
                                        )
                                ),

                        'gender' =>
                            $this
                                ->nullableString(
                                    $this
                                        ->rowValue(
                                            $row,
                                            [
                                                'gender',
                                                'sex',
                                            ]
                                        )
                                ),

                        'document_expiry_date' =>
                            $this
                                ->dateString(
                                    $this
                                        ->parseDate(
                                            $this
                                                ->rowValue(
                                                    $row,
                                                    [
                                                        'document expiry date',
                                                        'passport expiry date',
                                                        'id expiry date',
                                                    ]
                                                )
                                        )
                                ),

                        'last_recharge_date' =>
                            $this
                                ->dateString(
                                    $rechargeDate
                                ),

                        'installed_at' =>
                            $this
                                ->dateString(
                                    $installedAt
                                ),

                        'expiry_date' =>
                            $this
                                ->dateString(
                                    $expiryDate
                                ),

                        'billing_day' =>
                            $rechargeDate
                                ? $rechargeDate
                                    ->day
                                : (
                                    $expiryDate
                                        ? $expiryDate
                                            ->day
                                        : $today
                                            ->day
                                ),

                        'enabled' =>
                            $enabled,

                        'connected' =>
                            false,

                        'imported_at' =>
                            now(
                                'Asia/Qatar'
                            ),

                        'import_batch_uuid' =>
                            $batch,
                    ]);

                $client->forceFill([
                    'client_code' =>
                        'CLI-'
                        . str_pad(
                            (string)
                            $client->id,
                            5,
                            '0',
                            STR_PAD_LEFT
                        ),
                ])->save();

                /*
                 * Import custom fields WITHOUT calling
                 * validate(), because legacy migration
                 * must keep every custom field optional.
                 */
                if (
                    !empty(
                        $customPayload
                    )
                ) {
                    $this
                        ->customFields
                        ->sync(
                            $client,
                            $customPayload
                        );
                }

                /*
                 * IMPORTANT:
                 * No Invoice::create()
                 * No Payment::create()
                 *
                 * The client's existing old-system
                 * service period carries no opening
                 * money into MikroPanel.
                 */

                return $client;
            }
        );
    }

    private function customPayload(
        array $row,
        Collection $fields
    ): array {
        $payload = [];

        foreach (
            $fields
            as $field
        ) {
            $value =
                $this->rowValue(
                    $row,
                    [
                        $this->fieldHeader(
                            $field
                        ),
                        $field->field_key,
                        $field->name,
                    ]
                );

            if (
                $value === null
                || (
                    is_string($value)
                    && trim($value) === ''
                )
            ) {
                continue;
            }

            $payload[
                (string)
                $field->id
            ] =
                $this
                    ->normalizeCustomValue(
                        $field,
                        $value
                    );
        }

        return $payload;
    }

    private function normalizeCustomValue(
        ClientCustomField $field,
        mixed $value
    ): mixed {
        switch ($field->type) {
            case 'number':
                if (!is_numeric($value)) {
                    throw new RuntimeException(
                        $field->name
                        . ' must be numeric.'
                    );
                }

                return $value;

            case 'email':
                $value =
                    trim(
                        (string)
                        $value
                    );

                if (
                    !filter_var(
                        $value,
                        FILTER_VALIDATE_EMAIL
                    )
                ) {
                    throw new RuntimeException(
                        $field->name
                        . ' is not a valid email.'
                    );
                }

                return $value;

            case 'date':
                return $this
                    ->parseDate(
                        $value
                    )
                    ?->toDateString();

            case 'select':
                $value =
                    trim(
                        (string)
                        $value
                    );

                $options =
                    is_array(
                        $field->options
                    )
                        ? $field->options
                        : [];

                if (
                    !empty($options)
                    && !in_array(
                        $value,
                        $options,
                        true
                    )
                ) {
                    throw new RuntimeException(
                        $field->name
                        . ' has an invalid option.'
                    );
                }

                return $value;

            case 'boolean':
            case 'checkbox':
                $normalized =
                    strtolower(
                        trim(
                            (string)
                            $value
                        )
                    );

                if (
                    in_array(
                        $normalized,
                        [
                            '1',
                            'true',
                            'yes',
                            'y',
                            'on',
                        ],
                        true
                    )
                ) {
                    return true;
                }

                if (
                    in_array(
                        $normalized,
                        [
                            '0',
                            'false',
                            'no',
                            'n',
                            'off',
                        ],
                        true
                    )
                ) {
                    return false;
                }

                throw new RuntimeException(
                    $field->name
                    . ' must be Yes/No.'
                );

            default:
                return trim(
                    (string)
                    $value
                );
        }
    }

    private function resolvePackage(
        array $row,
        Package $default
    ): Package {
        $value =
            $this->nullableString(
                $this->rowValue(
                    $row,
                    [
                        'package',
                    ]
                )
            );

        if (!$value) {
            return $default;
        }

        $query =
            Package::query()
                ->where(
                    'enabled',
                    true
                );

        if (ctype_digit($value)) {
            return $query
                ->findOrFail(
                    (int)
                    $value
                );
        }

        return $query
            ->whereRaw(
                'LOWER(name) = ?',
                [
                    mb_strtolower(
                        $value
                    ),
                ]
            )
            ->firstOrFail();
    }

    private function resolveRange(
        array $row,
        IpRange $default
    ): IpRange {
        $value =
            $this->nullableString(
                $this->rowValue(
                    $row,
                    [
                        'ip pool',
                        'ip range',
                    ]
                )
            );

        if (!$value) {
            return $default;
        }

        $query =
            IpRange::query()
                ->where(
                    'enabled',
                    true
                );

        if (ctype_digit($value)) {
            return $query
                ->findOrFail(
                    (int)
                    $value
                );
        }

        return $query
            ->whereRaw(
                'LOWER(name) = ?',
                [
                    mb_strtolower(
                        $value
                    ),
                ]
            )
            ->firstOrFail();
    }

    private function headers(
        Collection $fields
    ): array {
        $headers = [
            'Client Name*',
            'Mobile Number*',
            'MAC Address*',
            'Package',
            'IP Pool',
            'Recharge Date',
            'Expiry Date',
            'Email',
            'Address',
            'Identity Type',
            'Identity Number',
            'Identity Barcode',
            'Nationality',
            'Date of Birth',
            'Gender',
            'Document Expiry Date',
            'Installed Date',
        ];

        foreach (
            $fields
            as $field
        ) {
            $headers[] =
                $this->fieldHeader(
                    $field
                );
        }

        return $headers;
    }

    private function fieldHeader(
        ClientCustomField $field
    ): string {
        return 'Custom: '
            . $field->name
            . ' ['
            . $field->field_key
            . ']';
    }

    private function rowValue(
        array $row,
        array $aliases
    ): mixed {
        $normalized = [];

        foreach (
            $row
            as $header => $value
        ) {
            $normalized[
                $this
                    ->normalizeHeader(
                        (string)
                        $header
                    )
            ] =
                $value;
        }

        foreach (
            $aliases
            as $alias
        ) {
            $key =
                $this
                    ->normalizeHeader(
                        (string)
                        $alias
                    );

            if (
                array_key_exists(
                    $key,
                    $normalized
                )
            ) {
                return $normalized[
                    $key
                ];
            }
        }

        return null;
    }

    private function normalizeHeader(
        string $value
    ): string {
        $value =
            mb_strtolower(
                trim($value)
            );

        $value =
            str_replace(
                '*',
                '',
                $value
            );

        $value =
            preg_replace(
                '/\s+/u',
                ' ',
                $value
            )
            ?? $value;

        return trim($value);
    }

    private function normalizeMac(
        string $value
    ): ?string {
        $hex =
            strtoupper(
                preg_replace(
                    '/[^0-9A-Fa-f]/',
                    '',
                    $value
                )
                ?? ''
            );

        if (
            strlen($hex)
            !== 12
        ) {
            return null;
        }

        return implode(
            ':',
            str_split(
                $hex,
                2
            )
        );
    }

    private function parseDate(
        mixed $value
    ): ?Carbon {
        if (
            $value === null
            || trim(
                (string)
                $value
            ) === ''
        ) {
            return null;
        }

        if (
            is_numeric($value)
            && (float)
                $value > 1000
        ) {
            try {
                return Carbon::instance(
                    ExcelDate::excelToDateTimeObject(
                        (float)
                        $value
                    )
                )->startOfDay();
            } catch (Throwable) {
                //
            }
        }

        $value =
            trim(
                (string)
                $value
            );

        foreach (
            [
                'Y-m-d',
                'd/m/Y',
                'd-m-Y',
                'd.m.Y',
                'm/d/Y',
            ]
            as $format
        ) {
            try {
                $date =
                    Carbon::createFromFormat(
                        $format,
                        $value,
                        'Asia/Qatar'
                    );

                if ($date) {
                    return $date
                        ->startOfDay();
                }
            } catch (Throwable) {
                //
            }
        }

        try {
            return Carbon::parse(
                $value,
                'Asia/Qatar'
            )->startOfDay();
        } catch (Throwable) {
            throw new RuntimeException(
                'Invalid date: '
                . $value
            );
        }
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value =
            trim(
                (string)
                $value
            );

        return $value !== ''
            ? $value
            : null;
    }

    private function dateString(
        ?Carbon $date
    ): ?string {
        return $date
            ?->toDateString();
    }

    private function save(
        Spreadsheet $spreadsheet,
        string $prefix
    ): string {
        $directory =
            storage_path(
                'app/tmp'
            );

        if (!is_dir($directory)) {
            mkdir(
                $directory,
                0755,
                true
            );
        }

        $path =
            $directory
            . '/'
            . $prefix
            . '-'
            . Str::uuid()
            . '.xlsx';

        $writer =
            new Xlsx(
                $spreadsheet
            );

        $writer->save(
            $path
        );

        $spreadsheet
            ->disconnectWorksheets();

        return $path;
    }
}
