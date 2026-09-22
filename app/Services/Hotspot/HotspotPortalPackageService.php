<?php

namespace App\Services\Hotspot;

use App\Services\CompanyBrandingService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class HotspotPortalPackageService
{
    private const TEMPLATE_SHA256 =
        '9e54cadc51bf328280c0079eb89f2463bf41c2b8547fe04ddd1896ece2395f71';

    public function __construct(
        private readonly CompanyBrandingService $branding
    ) {
    }

    /**
     * Build the package using the reseller's existing
     * Company Settings branding.
     *
     * @return array{
     *     path:string,
     *     filename:string,
     *     branding:array
     * }
     */
    public function build(
        ?int $resellerId
    ): array {
        return $this->buildFromBranding(
            $this->branding->forResellerId(
                $resellerId,
                true
            )
        );
    }

    /**
     * Kept separately so package generation can be
     * verified without writing anything to the DB.
     *
     * @return array{
     *     path:string,
     *     filename:string,
     *     branding:array
     * }
     */
    public function buildFromBranding(
        array $branding
    ): array {
        $template =
            resource_path(
                'hotspot-template/Hotspot.zip'
            );

        if (
            !is_file($template)
            || !is_readable($template)
        ) {
            throw new RuntimeException(
                'Hotspot master template is missing.'
            );
        }

        if (
            hash_file('sha256', $template)
            !== self::TEMPLATE_SHA256
        ) {
            throw new RuntimeException(
                'Hotspot master template checksum mismatch.'
            );
        }

        $companyName =
            trim(
                (string) (
                    $branding['company_name']
                    ?? ''
                )
            );

        if ($companyName === '') {
            $companyName =
                'WiFi Service';
        }

        $panelName =
            trim(
                (string) (
                    $branding['panel_name']
                    ?? ''
                )
            );

        if ($panelName === '') {
            $panelName =
                $companyName;
        }

        $branding['company_name'] =
            $companyName;

        $branding['panel_name'] =
            $panelName;

        $source =
            new ZipArchive();

        if (
            $source->open($template)
            !== true
        ) {
            throw new RuntimeException(
                'Unable to open Hotspot master template.'
            );
        }

        $directory =
            storage_path(
                'app/tmp/hotspot-portals'
            );

        File::ensureDirectoryExists(
            $directory,
            0750,
            true
        );

        $path =
            tempnam(
                $directory,
                'portal-'
            );

        if ($path === false) {
            $source->close();

            throw new RuntimeException(
                'Unable to create temporary package.'
            );
        }

        $output =
            new ZipArchive();

        $opened =
            $output->open(
                $path,
                ZipArchive::CREATE
                | ZipArchive::OVERWRITE
            );

        if ($opened !== true) {
            $source->close();
            @unlink($path);

            throw new RuntimeException(
                'Unable to create Hotspot ZIP.'
            );
        }

        try {
            for (
                $index = 0;
                $index < $source->numFiles;
                $index++
            ) {
                $name =
                    $source->getNameIndex(
                        $index
                    );

                if (
                    !is_string($name)
                    || $name === ''
                ) {
                    continue;
                }

                if (
                    str_contains(
                        $name,
                        '..'
                    )
                    || !str_starts_with(
                        $name,
                        'Hotspot/'
                    )
                ) {
                    throw new RuntimeException(
                        'Unexpected Hotspot template entry.'
                    );
                }

                if (
                    str_ends_with(
                        $name,
                        '/'
                    )
                ) {
                    $output->addEmptyDir(
                        rtrim(
                            $name,
                            '/'
                        )
                    );

                    continue;
                }

                $contents =
                    $source->getFromIndex(
                        $index
                    );

                if ($contents === false) {
                    throw new RuntimeException(
                        'Unable to read template entry: '
                        . $name
                    );
                }

                if (
                    $name
                    === 'Hotspot/logo.svg'
                ) {
                    $contents =
                        $this->logoSvg(
                            $branding
                        );
                } elseif (
                    $this->isTextFile(
                        $name
                    )
                ) {
                    $contents =
                        $this->brandText(
                            $contents,
                            $name,
                            $panelName,
                            $companyName
                        );
                }

                if (
                    !$output->addFromString(
                        $name,
                        $contents
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to write package entry: '
                        . $name
                    );
                }
            }
        } catch (\Throwable $exception) {
            $output->close();
            $source->close();
            @unlink($path);

            throw $exception;
        }

        $output->close();
        $source->close();

        @chmod(
            $path,
            0600
        );

        $slug =
            Str::slug(
                $panelName
            );

        if ($slug === '') {
            $slug =
                'company';
        }

        return [
            'path' =>
                $path,

            'filename' =>
                $slug
                . '-hotspot-portal.zip',

            'branding' =>
                $branding,
        ];
    }

    private function isTextFile(
        string $name
    ): bool {
        return in_array(
            strtolower(
                pathinfo(
                    $name,
                    PATHINFO_EXTENSION
                )
            ),
            [
                'html',
                'js',
                'css',
                'json',
                'txt',
            ],
            true
        );
    }

    private function brandText(
        string $contents,
        string $name,
        string $panelName,
        string $companyName
    ): string {
        $extension =
            strtolower(
                pathinfo(
                    $name,
                    PATHINFO_EXTENSION
                )
            );

        if ($extension === 'html') {
            $panel =
                htmlspecialchars(
                    $panelName,
                    ENT_QUOTES
                    | ENT_SUBSTITUTE,
                    'UTF-8'
                );

            $company =
                htmlspecialchars(
                    $companyName,
                    ENT_QUOTES
                    | ENT_SUBSTITUTE,
                    'UTF-8'
                );
        } elseif (
            in_array(
                $extension,
                [
                    'js',
                    'json',
                ],
                true
            )
        ) {
            $panel =
                $this->javascriptText(
                    $panelName
                );

            $company =
                $this->javascriptText(
                    $companyName
                );
        } else {
            $panel =
                $panelName;

            $company =
                $companyName;
        }

        /*
         * Longest values must be replaced first.
         */
        $contents =
            str_replace(
                [
                    'Genius Information Technology W.L.L',
                    'Genius Wi-Fi',
                    'Genius Wi-Fi',
                    'Genius',
                ],
                [
                    $company,
                    $panel . ' Wi-Fi',
                    $panel . ' Wi-Fi',
                    $panel,
                ],
                $contents
            );

        /*
         * Do not keep one reseller's language preference
         * key branded as another company.
         */
        return str_replace(
            'genius_lang',
            'mikropanel_hotspot_lang',
            $contents
        );
    }

    private function javascriptText(
        string $value
    ): string {
        $encoded =
            json_encode(
                $value,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            );

        if (
            !is_string($encoded)
            || strlen($encoded) < 2
        ) {
            return '';
        }

        return substr(
            $encoded,
            1,
            -1
        );
    }

    private function logoSvg(
        array $branding
    ): string {
        $dataUri =
            trim(
                (string) (
                    $branding[
                        'company_logo_data_uri'
                    ]
                    ?? ''
                )
            );

        if ($dataUri !== '') {
            $source =
                htmlspecialchars(
                    $dataUri,
                    ENT_QUOTES
                    | ENT_XML1,
                    'UTF-8'
                );

            return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160" role="img" aria-label="Company logo">
  <rect width="160" height="160" rx="32" fill="#ffffff"/>
  <image href="{$source}" x="8" y="8" width="144" height="144" preserveAspectRatio="xMidYMid meet"/>
</svg>
SVG;
        }

        $mark =
            trim(
                (string) (
                    $branding['brand_mark']
                    ?? ''
                )
            );

        if ($mark === '') {
            $mark =
                $this->initials(
                    (string) (
                        $branding[
                            'company_name'
                        ]
                        ?? 'WiFi'
                    )
                );
        }

        $mark =
            htmlspecialchars(
                mb_substr(
                    $mark,
                    0,
                    4
                ),
                ENT_QUOTES
                | ENT_XML1,
                'UTF-8'
            );

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 160" role="img" aria-label="Company logo">
  <rect width="160" height="160" rx="36" fill="#0f3d73"/>
  <circle cx="80" cy="80" r="58" fill="#ffffff" opacity=".12"/>
  <text x="80" y="94" text-anchor="middle" font-family="Arial, sans-serif" font-size="48" font-weight="700" fill="#ffffff">{$mark}</text>
</svg>
SVG;
    }

    private function initials(
        string $name
    ): string {
        $words =
            preg_split(
                '/\s+/u',
                trim($name)
            )
            ?: [];

        $letters = '';

        foreach ($words as $word) {
            if ($word === '') {
                continue;
            }

            $letters .=
                mb_substr(
                    $word,
                    0,
                    1
                );

            if (
                mb_strlen(
                    $letters
                ) >= 3
            ) {
                break;
            }
        }

        return mb_strtoupper(
            $letters !== ''
                ? $letters
                : 'W'
        );
    }
}
