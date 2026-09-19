<?php

namespace App\Services;

use App\Models\Reseller;
use App\Models\ResellerSetting;
use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

class CompanyBrandingService
{
    public function forResellerId(
        ?int $resellerId,
        bool $includeDataUri = false
    ): array {
        if (!$resellerId) {
            return $this->globalFallback(
                $includeDataUri
            );
        }

        $reseller =
            Reseller::query()
                ->find($resellerId);

        if (!$reseller) {
            return $this->globalFallback(
                $includeDataUri
            );
        }

        $saved =
            ResellerSetting::allDecodedFor(
                $reseller->id
            );

        $logoPath =
            $saved['company_logo_path']
            ?? null;

        $logoUrl =
            $this->publicUrl(
                $logoPath
            );

        $companyName =
            trim(
                (string)
                $reseller->company_name
            );

        $panelName =
            trim(
                (string) (
                    $saved['panel_name']
                    ?? ''
                )
            );

        if ($panelName === '') {
            $panelName =
                $companyName;
        }

        $result = [
            'reseller_id' =>
                $reseller->id,

            'panel_name' =>
                $panelName,

            'company_name' =>
                $companyName,

            'company_phone' =>
                $reseller->phone,

            'company_email' =>
                $reseller->email,

            'company_address' =>
                $reseller->address,

            'website' =>
                $saved['website']
                ?? null,

            'currency' =>
                $reseller->currency
                ?: 'QAR',

            'company_logo_path' =>
                $logoPath,

            'company_logo_url' =>
                $logoUrl,

            'invoice_terms' =>
                $saved['invoice_terms']
                ?? 'Thank you for your business.',

            'invoice_footer' =>
                $saved['invoice_footer']
                ?? 'Internet service is subject to the applicable package terms.',

            'authorized_signature' =>
                $saved['authorized_signature']
                ?? 'Authorized Signature',

            'show_logo_on_documents' =>
                array_key_exists(
                    'show_logo_on_documents',
                    $saved
                )
                    ? (bool)
                        $saved[
                            'show_logo_on_documents'
                        ]
                    : true,

            'brand_mark' =>
                $this->initials(
                    $companyName
                ),
        ];

        if ($includeDataUri) {
            $result[
                'company_logo_data_uri'
            ] =
                $this->dataUri(
                    $logoPath
                );
        }

        return $result;
    }

    private function globalFallback(
        bool $includeDataUri
    ): array {
        $logoPath =
            Setting::getValue(
                'company_logo_path'
            );

        $companyName =
            (string)
            Setting::getValue(
                'company_name',
                'MikroPanel'
            );

        $result = [
            'reseller_id' => null,

            'panel_name' =>
                Setting::getValue(
                    'panel_name',
                    'MikroPanel'
                ),

            'company_name' =>
                $companyName,

            'company_phone' =>
                Setting::getValue(
                    'company_phone'
                ),

            'company_email' =>
                Setting::getValue(
                    'company_email'
                ),

            'company_address' =>
                Setting::getValue(
                    'company_address'
                ),

            'website' =>
                Setting::getValue(
                    'website'
                ),

            'currency' =>
                Setting::getValue(
                    'currency_code',
                    'QAR'
                ),

            'company_logo_path' =>
                $logoPath,

            'company_logo_url' =>
                $this->publicUrl(
                    $logoPath
                ),

            'invoice_terms' =>
                Setting::getValue(
                    'invoice_terms',
                    'Thank you for your business.'
                ),

            'invoice_footer' =>
                Setting::getValue(
                    'invoice_footer',
                    ''
                ),

            'authorized_signature' =>
                Setting::getValue(
                    'authorized_signature',
                    'Authorized Signature'
                ),

            'show_logo_on_documents' =>
                Setting::bool(
                    'show_logo_on_documents',
                    true
                ),

            'brand_mark' =>
                $this->initials(
                    $companyName
                ),
        ];

        if ($includeDataUri) {
            $result[
                'company_logo_data_uri'
            ] =
                $this->dataUri(
                    $logoPath
                );
        }

        return $result;
    }

    private function publicUrl(
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

    private function dataUri(
        ?string $path
    ): ?string {
        if (
            !$path
            || !Storage::disk('public')
                ->exists($path)
        ) {
            return null;
        }

        $absolute =
            Storage::disk('public')
                ->path($path);

        $mime =
            mime_content_type(
                $absolute
            )
            ?: 'image/png';

        return sprintf(
            'data:%s;base64,%s',
            $mime,
            base64_encode(
                file_get_contents(
                    $absolute
                )
            )
        );
    }

    private function initials(
        string $name
    ): string {
        $parts =
            preg_split(
                '/\s+/',
                trim($name)
            )
            ?: [];

        $letters = '';

        foreach (
            array_slice(
                $parts,
                0,
                3
            )
            as $part
        ) {
            if ($part !== '') {
                $letters .=
                    mb_strtoupper(
                        mb_substr(
                            $part,
                            0,
                            1
                        )
                    );
            }
        }

        return $letters !== ''
            ? $letters
            : 'CO';
    }
}
