<?php

namespace App\Services\Hotspot;

use App\Models\Router;
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

    /*
     * MAIN_HOTSPOT_MAC_RESET_PACKAGE_V1
     */
    public function buildForRouter(
        Router $router
    ): array {
        $router->loadMissing(
            'zone:id,reseller_id'
        );

        $resellerId =
            $router->zone
                ?->reseller_id;

        $branding =
            $this->branding
                ->forResellerId(
                    $resellerId
                        ? (int) $resellerId
                        : null,
                    true
                );

        $branding[
            '_hotspot_mac_reset_url'
        ] =
            route(
                'hotspot.portal.mac-reset',
                [
                    'router' =>
                        $router->id,

                    'token' =>
                        $this->resetToken(
                            $router
                        ),
                ]
            );

        return $this->buildFromBranding(
            $branding
        );
    }

    public function resetToken(
        Router $router
    ): string {
        $key =
            (string)
            config(
                'app.key'
            );

        if ($key === '') {
            throw new RuntimeException(
                'Application key is unavailable.'
            );
        }

        return hash_hmac(
            'sha256',
            'main-hotspot-mac-reset|'
            . $router->id
            . '|'
            . (
                $router->zone_id
                ?? 0
            ),
            $key
        );
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

        $macResetUrl =
            trim(
                (string) (
                    $branding[
                        '_hotspot_mac_reset_url'
                    ]
                    ?? ''
                )
            );

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
                            $companyName,
                            $macResetUrl
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
        string $companyName,
        string $macResetUrl
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
        $contents =
            str_replace(
                'genius_lang',
                'mikropanel_hotspot_lang',
                $contents
            );

        if ($macResetUrl !== '') {
            if (
                $name === 'Hotspot/login.html'
            ) {
                $contents =
                    $this->injectMacReset(
                        $contents,
                        $macResetUrl
                    );

            } elseif (
                $name === 'Hotspot/status.html'
            ) {
                $contents =
                    $this->injectStatusMacReset(
                        $contents,
                        $macResetUrl
                    );
            }
        }

        return $contents;
    }

    /*
     * MAIN_HOTSPOT_MAC_RESET_PACKAGE_V1
     */
    private function injectMacReset(
        string $contents,
        string $resetUrl
    ): string {
        $safeUrl =
            htmlspecialchars(
                $resetUrl,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            );

        $pattern =
            '~<div><strong data-i18n="move_title">'
            . '.*?</strong>\s*'
            . '<small data-i18n="move_desc">'
            . '.*?</small></div>~s';

        $replacement = <<<HTML
<div>
  <strong>MAC Reset / Use Voucher on New Device</strong>
  <small>Enter the same 6-digit Voucher Code above, then tap MAC Reset.</small>
</div>
<button
  class="btn btn-outline-danger"
  id="mac-reset-btn"
  type="button"
  data-reset-url="{$safeUrl}"
  style="width:100%;margin-top:12px"
>
  ↻ MAC Reset / Release Voucher
</button>
<div
  id="mac-reset-result"
  style="margin-top:10px;font-size:14px;font-weight:700"
  aria-live="polite"
></div>
HTML;

        $contents =
            preg_replace(
                $pattern,
                $replacement,
                $contents,
                1,
                $count
            );

        if (
            !is_string($contents)
            || $count !== 1
        ) {
            throw new RuntimeException(
                'MAC Reset portal section could not be injected.'
            );
        }

        $script = <<<'HTML'
<script>
(function () {
  var button = document.getElementById('mac-reset-btn');
  var input = document.getElementById('voucher');
  var result = document.getElementById('mac-reset-result');

  if (!button || !input || !result) {
    return;
  }

  button.addEventListener('click', async function () {
    var code = (input.value || '').replace(/\D/g, '').slice(0, 6);

    input.value = code;

    if (!/^[0-9]{6}$/.test(code)) {
      result.style.color = '#dc2626';
      result.textContent = 'Enter your 6-digit Voucher Code first.';
      input.focus();
      return;
    }

    var url = button.getAttribute('data-reset-url');

    if (!url) {
      result.style.color = '#dc2626';
      result.textContent = 'MAC Reset is unavailable.';
      return;
    }

    button.disabled = true;
    result.style.color = '#475569';
    result.textContent = 'Resetting voucher MAC...';

    try {
      var response = await fetch(url, {
        method: 'POST',
        mode: 'cors',
        credentials: 'omit',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
        },
        body: 'voucher_code=' + encodeURIComponent(code)
      });

      var data = {};

      try {
        data = await response.json();
      } catch (_) {
        data = {};
      }

      if (!response.ok) {
        throw new Error(
          data.message || 'MAC Reset failed. Please try again.'
        );
      }

      result.style.color = '#15803d';
      result.textContent =
        data.message ||
        'MAC reset successful. You can now login on the new device.';
    } catch (error) {
      result.style.color = '#dc2626';
      result.textContent =
        error && error.message
          ? error.message
          : 'MAC Reset failed. Please try again.';
    } finally {
      button.disabled = false;
    }
  });
})();
</script>
HTML;

        if (
            !str_contains(
                $contents,
                '</body>'
            )
        ) {
            throw new RuntimeException(
                'Portal closing body tag is missing.'
            );
        }

        return str_replace(
            '</body>',
            $script
            . "
</body>",
            $contents
        );
    }

    /*
     * MAIN_HOTSPOT_STATUS_MAC_RESET_V1
     *
     * The logged-in status page must use the same
     * real backend reset as the login page.
     */
    private function injectStatusMacReset(
        string $contents,
        string $resetUrl
    ): string {
        $safeUrl =
            htmlspecialchars(
                $resetUrl,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            );

        $oldButton =
            '<a class="btn btn-outline-danger" '
            . 'href="$(link-logout)?erase-cookie=on">'
            . '↻ <span data-i18n="release">'
            . 'MAC Reset / Release Voucher'
            . '</span></a>';

        if (
            !str_contains(
                $contents,
                $oldButton
            )
        ) {
            throw new RuntimeException(
                'Status MAC Reset button was not found.'
            );
        }

        $button = <<<HTML
<button
  class="btn btn-outline-danger"
  id="status-mac-reset-btn"
  type="button"
  data-reset-url="{$safeUrl}"
  data-voucher="\$(username)"
>
  ↻ MAC Reset / Release Voucher
</button>
<div
  id="status-mac-reset-result"
  style="margin-top:10px;font-size:14px;font-weight:700"
  aria-live="polite"
></div>
HTML;

        $contents =
            str_replace(
                $oldButton,
                $button,
                $contents
            );

        /*
         * Do not let old portal.js translation describe
         * this as cookie-only release.
         */
        $contents =
            str_replace(
                'data-i18n="release_desc"',
                'data-reset-description="true"',
                $contents
            );

        $contents =
            str_replace(
                'Log out this device and erase the Hotspot browser cookie so the voucher is ready for another device.',
                'Clear this voucher MAC binding and disconnect this device so the voucher can be used on another device.',
                $contents
            );

        $script = <<<'HTML'
<script>
(function () {
  var button =
    document.getElementById(
      'status-mac-reset-btn'
    );

  var result =
    document.getElementById(
      'status-mac-reset-result'
    );

  if (!button || !result) {
    return;
  }

  button.addEventListener(
    'click',
    async function () {
      var code =
        (
          button.getAttribute(
            'data-voucher'
          )
          || ''
        )
          .replace(/\D/g, '')
          .slice(0, 6);

      if (!/^[0-9]{6}$/.test(code)) {
        result.style.color =
          '#dc2626';

        result.textContent =
          'Voucher Code is unavailable.';

        return;
      }

      var url =
        button.getAttribute(
          'data-reset-url'
        );

      if (!url) {
        result.style.color =
          '#dc2626';

        result.textContent =
          'MAC Reset is unavailable.';

        return;
      }

      button.disabled = true;

      result.style.color =
        '#475569';

      result.textContent =
        'Resetting voucher MAC...';

      try {
        var response =
          await fetch(
            url,
            {
              method: 'POST',
              mode: 'cors',
              credentials: 'omit',
              headers: {
                'Accept':
                  'application/json',

                'Content-Type':
                  'application/x-www-form-urlencoded;charset=UTF-8'
              },

              body:
                'voucher_code='
                + encodeURIComponent(
                    code
                  )
            }
          );

        var data = {};

        try {
          data =
            await response.json();
        } catch (_) {
          data = {};
        }

        if (!response.ok) {
          throw new Error(
            data.message
            || 'MAC Reset failed. Please try again.'
          );
        }

        result.style.color =
          '#15803d';

        result.textContent =
          data.message
          || 'MAC reset successful.';

      } catch (error) {
        result.style.color =
          '#dc2626';

        result.textContent =
          error
          && error.message
            ? error.message
            : 'MAC Reset failed. Please try again.';

      } finally {
        button.disabled = false;
      }
    }
  );
})();
</script>
HTML;

        if (
            !str_contains(
                $contents,
                '</body>'
            )
        ) {
            throw new RuntimeException(
                'Status closing body tag is missing.'
            );
        }

        return str_replace(
            '</body>',
            $script
            . "\n</body>",
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
