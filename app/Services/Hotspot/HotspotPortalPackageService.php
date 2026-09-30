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
            0770,
            true
        );

        /*
         * Never allow tempnam() to silently fall
         * back to system /tmp. Laravel converts
         * that warning into an exception.
         *
         * If an old deployment created the child
         * directory with wrong ownership, use the
         * writable application tmp parent.
         */
        if (!is_writable($directory)) {
            $directory =
                storage_path(
                    'app/tmp'
                );

            File::ensureDirectoryExists(
                $directory,
                0770,
                true
            );
        }

        if (!is_writable($directory)) {
            $source->close();

            throw new RuntimeException(
                'Hotspot package temporary directory is not writable.'
            );
        }

        $path =
            $directory
            . DIRECTORY_SEPARATOR
            . 'portal-'
            . Str::uuid()
                ->toString()
            . '.zip';

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
    /*
     * MAIN_HOTSPOT_TWO_STEP_MAC_RESET_UI_V1
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
  <strong>MAC Reset / Release Voucher</strong>
  <small>Check the voucher and review its current device before resetting.</small>
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

        return $this->appendMacResetModal(
            $contents,
            'mac-reset-btn'
        );
    }

    /*
     * MAIN_HOTSPOT_STATUS_MAC_RESET_V1
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
>
  ↻ <span data-i18n="release">MAC Reset / Release Voucher</span>
</button>
HTML;

        $contents =
            str_replace(
                $oldButton,
                $button,
                $contents
            );

        $contents =
            str_replace(
                'data-i18n="release_desc"',
                'data-reset-description="true"',
                $contents
            );

        $contents =
            str_replace(
                'Log out this device and erase the Hotspot browser cookie so the voucher is ready for another device.',
                'Check the voucher and review its current device before resetting.',
                $contents
            );

        return $this->appendMacResetModal(
            $contents,
            'status-mac-reset-btn'
        );
    }

    private function appendMacResetModal(
        string $contents,
        string $triggerId
    ): string {
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

        $safeTrigger =
            htmlspecialchars(
                $triggerId,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            );

        $ui = <<<HTML
<div
  id="voucher-reset-modal"
  style="
    display:none;
    position:fixed;
    inset:0;
    z-index:99999;
    background:rgba(15,23,42,.72);
    padding:18px;
    align-items:center;
    justify-content:center;
  "
>
  <div
    style="
      width:100%;
      max-width:430px;
      max-height:90vh;
      overflow:auto;
      background:#fff;
      border-radius:20px;
      padding:22px;
      box-shadow:0 24px 70px rgba(0,0,0,.28);
      color:#0f172a;
      text-align:left;
    "
  >
    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px">
      <div>
        <div style="font-size:20px;font-weight:800">
          MAC Reset / Release Voucher
        </div>
        <div style="margin-top:4px;font-size:13px;color:#64748b">
          Verify the voucher before any reset is performed.
        </div>
      </div>

      <button
        id="voucher-reset-close"
        type="button"
        aria-label="Close"
        style="
          border:0;
          background:#f1f5f9;
          width:36px;
          height:36px;
          border-radius:50%;
          font-size:22px;
          cursor:pointer;
        "
      >×</button>
    </div>

    <div
      id="voucher-reset-check-step"
      style="margin-top:20px"
    >
      <label
        for="voucher-reset-code"
        style="
          display:block;
          font-size:13px;
          font-weight:700;
          margin-bottom:7px;
        "
      >
        6-digit Voucher Code
      </label>

      <input
        id="voucher-reset-code"
        type="text"
        inputmode="numeric"
        pattern="[0-9]{6}"
        minlength="6"
        maxlength="6"
        autocomplete="one-time-code"
        placeholder="Enter 6-digit voucher"
        style="
          width:100%;
          box-sizing:border-box;
          border:1px solid #cbd5e1;
          border-radius:12px;
          padding:13px 14px;
          font-size:18px;
          letter-spacing:3px;
          text-align:center;
          outline:none;
        "
      >

      <button
        id="voucher-reset-check"
        type="button"
        class="btn btn-primary"
        style="width:100%;margin-top:12px"
      >
        Check Voucher
      </button>
    </div>

    <div
      id="voucher-device-info"
      style="
        display:none;
        margin-top:18px;
        border:1px solid #e2e8f0;
        border-radius:15px;
        overflow:hidden;
      "
    >
      <div
        style="
          padding:12px 14px;
          background:#f8fafc;
          font-size:14px;
          font-weight:800;
        "
      >
        Current Device Information
      </div>

      <div style="padding:12px 14px;font-size:14px">
        <div style="display:flex;justify-content:space-between;gap:14px;margin:7px 0">
          <span style="color:#64748b">Status</span>
          <strong id="reset-info-status">—</strong>
        </div>

        <div style="display:flex;justify-content:space-between;gap:14px;margin:7px 0">
          <span style="color:#64748b">MAC Address</span>
          <strong id="reset-info-mac">—</strong>
        </div>

        <div style="display:flex;justify-content:space-between;gap:14px;margin:7px 0">
          <span style="color:#64748b">IP Address</span>
          <strong id="reset-info-ip">—</strong>
        </div>

        <div style="display:flex;justify-content:space-between;gap:14px;margin:7px 0">
          <span style="color:#64748b">Login By</span>
          <strong id="reset-info-login">—</strong>
        </div>

        <div style="display:flex;justify-content:space-between;gap:14px;margin:7px 0">
          <span style="color:#64748b">Uptime</span>
          <strong id="reset-info-uptime">—</strong>
        </div>

        <div style="display:flex;justify-content:space-between;gap:14px;margin:7px 0">
          <span style="color:#64748b">Hotspot</span>
          <strong id="reset-info-server">—</strong>
        </div>

        <div style="display:flex;justify-content:space-between;gap:14px;margin:7px 0">
          <span style="color:#64748b">Router</span>
          <strong id="reset-info-router">—</strong>
        </div>
      </div>
    </div>

    <button
      id="voucher-reset-confirm"
      type="button"
      class="btn btn-outline-danger"
      style="
        display:none;
        width:100%;
        margin-top:14px;
      "
    >
      ↻ Reset Voucher
    </button>

    <div
      id="voucher-reset-message"
      aria-live="polite"
      style="
        min-height:20px;
        margin-top:12px;
        font-size:13px;
        font-weight:700;
        text-align:center;
      "
    ></div>
  </div>
</div>

<script>
(function () {
  var trigger =
    document.getElementById(
      '{$safeTrigger}'
    );

  var modal =
    document.getElementById(
      'voucher-reset-modal'
    );

  var closeButton =
    document.getElementById(
      'voucher-reset-close'
    );

  var codeInput =
    document.getElementById(
      'voucher-reset-code'
    );

  var checkButton =
    document.getElementById(
      'voucher-reset-check'
    );

  var confirmButton =
    document.getElementById(
      'voucher-reset-confirm'
    );

  var deviceBox =
    document.getElementById(
      'voucher-device-info'
    );

  var message =
    document.getElementById(
      'voucher-reset-message'
    );

  if (
    !trigger
    || !modal
    || !codeInput
    || !checkButton
    || !confirmButton
    || !message
  ) {
    return;
  }

  var confirmationToken = '';
  var verifiedCode = '';

  function setMessage(
    text,
    success
  ) {
    message.style.color =
      success
        ? '#15803d'
        : '#dc2626';

    message.textContent =
      text || '';
  }

  function valueOrDash(value) {
    if (
      value === null
      || value === undefined
      || value === ''
    ) {
      return '—';
    }

    return String(value);
  }

  function resetView() {
    confirmationToken = '';
    verifiedCode = '';

    codeInput.value = '';

    deviceBox.style.display =
      'none';

    confirmButton.style.display =
      'none';

    checkButton.disabled =
      false;

    confirmButton.disabled =
      false;

    message.textContent =
      '';
  }

  function openModal() {
    resetView();

    modal.style.display =
      'flex';

    setTimeout(
      function () {
        codeInput.focus();
      },
      30
    );
  }

  function closeModal() {
    modal.style.display =
      'none';

    resetView();
  }

  async function sendRequest(payload) {
    var url =
      trigger.getAttribute(
        'data-reset-url'
      );

    if (!url) {
      throw new Error(
        'MAC Reset is unavailable.'
      );
    }

    var body =
      Object.keys(payload)
        .map(
          function (key) {
            return (
              encodeURIComponent(key)
              + '='
              + encodeURIComponent(
                  payload[key]
              )
            );
          }
        )
        .join('&');

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

          body: body
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
        || 'Request failed. Please try again.'
      );
    }

    return data;
  }

  trigger.addEventListener(
    'click',
    openModal
  );

  closeButton.addEventListener(
    'click',
    closeModal
  );

  modal.addEventListener(
    'click',
    function (event) {
      if (event.target === modal) {
        closeModal();
      }
    }
  );

  codeInput.addEventListener(
    'input',
    function () {
      codeInput.value =
        (codeInput.value || '')
          .replace(/\D/g, '')
          .slice(0, 6);
    }
  );

  checkButton.addEventListener(
    'click',
    async function () {
      var code =
        (codeInput.value || '')
          .replace(/\D/g, '')
          .slice(0, 6);

      codeInput.value =
        code;

      if (!/^[0-9]{6}$/.test(code)) {
        setMessage(
          'Enter a valid 6-digit Voucher Code.',
          false
        );

        codeInput.focus();
        return;
      }

      checkButton.disabled =
        true;

      confirmationToken = '';
      verifiedCode = '';

      deviceBox.style.display =
        'none';

      confirmButton.style.display =
        'none';

      message.style.color =
        '#475569';

      message.textContent =
        'Checking voucher and current device...';

      try {
        var data =
          await sendRequest({
            action: 'inspect',
            voucher_code: code
          });

        var device =
          data.device || {};

        verifiedCode =
          code;

        confirmationToken =
          data.confirm_token
          || '';

        document.getElementById(
          'reset-info-status'
        ).textContent =
          device.online
            ? 'Online'
            : 'Offline';

        document.getElementById(
          'reset-info-mac'
        ).textContent =
          valueOrDash(
            device.mac_address
          );

        document.getElementById(
          'reset-info-ip'
        ).textContent =
          valueOrDash(
            device.ip_address
          );

        document.getElementById(
          'reset-info-login'
        ).textContent =
          valueOrDash(
            device.login_by
          );

        document.getElementById(
          'reset-info-uptime'
        ).textContent =
          valueOrDash(
            device.uptime
          );

        document.getElementById(
          'reset-info-server'
        ).textContent =
          valueOrDash(
            device.hotspot_server
          );

        document.getElementById(
          'reset-info-router'
        ).textContent =
          valueOrDash(
            device.router_name
          );

        deviceBox.style.display =
          'block';

        confirmButton.style.display =
          'block';

        setMessage(
          'Voucher verified. Check the device information, then tap Reset Voucher.',
          true
        );

      } catch (error) {
        setMessage(
          error && error.message
            ? error.message
            : 'Voucher check failed.',
          false
        );

      } finally {
        checkButton.disabled =
          false;
      }
    }
  );

  confirmButton.addEventListener(
    'click',
    async function () {
      if (
        !verifiedCode
        || !confirmationToken
      ) {
        setMessage(
          'Check the voucher again before resetting.',
          false
        );

        return;
      }

      confirmButton.disabled =
        true;

      message.style.color =
        '#475569';

      message.textContent =
        'Resetting voucher...';

      try {
        var data =
          await sendRequest({
            action: 'reset',
            voucher_code:
              verifiedCode,
            confirm_token:
              confirmationToken
          });

        confirmationToken =
          '';

        deviceBox.style.display =
          'none';

        confirmButton.style.display =
          'none';

        setMessage(
          data.message
          || 'Voucher reset successful.',
          true
        );

      } catch (error) {
        setMessage(
          error && error.message
            ? error.message
            : 'Voucher reset failed.',
          false
        );

      } finally {
        confirmButton.disabled =
          false;
      }
    }
  );
})();
</script>
HTML;

        return str_replace(
            '</body>',
            $ui
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
