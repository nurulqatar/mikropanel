<?php

namespace App\Services\Hotel;

use App\Models\Hotel\HotelRouter;
use RouterOS\Client;
use RouterOS\Config;
use RouterOS\Query;
use RuntimeException;

class HotelCaptivePortalDeployService
{
    /*
     * HOTEL_CAPTIVE_AUTO_DEPLOY_V1
     *
     * MikroTik only stores a tiny bridge.
     * The commercial Hotel portal remains on MikroPanel,
     * so branding/UI updates do not require router uploads.
     */
    public function deploy(
        HotelRouter $router
    ): array {
        $router->loadMissing(
            'hotel'
        );

        $hotel =
            $router->hotel;

        if (!$hotel) {
            throw new RuntimeException(
                'Hotel was not found for this router.'
            );
        }

        $portalUrl =
            route(
                'hotel.portal.welcome',
                [
                    'hotel' =>
                        $hotel->slug,
                ],
                true
            );

        $portalHost =
            (string)
            parse_url(
                $portalUrl,
                PHP_URL_HOST
            );

        if ($portalHost === '') {
            throw new RuntimeException(
                'Hotel portal hostname is invalid.'
            );
        }

        $client =
            $this->client(
                $router
            );

        $server =
            $this->hotspotServer(
                $client,
                $router
            );

        $serverProfile =
            trim(
                (string) (
                    $server['profile']
                    ?? ''
                )
            );

        if ($serverProfile === '') {
            throw new RuntimeException(
                'MikroTik HotSpot server profile was not detected.'
            );
        }

        $profile =
            $this->hotspotProfile(
                $client,
                $serverProfile
            );

        $directory =
            $this->portalDirectory(
                $client,
                $router
            );

        $this->ensureDirectory(
            $client,
            $directory
        );

        $files =
            $this->files(
                $portalUrl,
                $router->id,
                $hotel->name
            );

        foreach (
            $files
            as $name => $contents
        ) {
            if (
                strlen($contents)
                > 60000
            ) {
                throw new RuntimeException(
                    "Captive file {$name} exceeds RouterOS editable file limit."
                );
            }

            $this->putFile(
                $client,
                $directory
                    . '/'
                    . $name,
                $contents
            );
        }

        /*
         * Important:
         * This is the HotSpot SERVER profile detected from
         * /ip/hotspot/print.
         *
         * It is NOT HotelRouter::hotspot_profile_name,
         * which is used by Hotel voucher users.
         */
        /*
         * RouterOS stores file-list names as flash/...
         * but HotSpot html-directory expects the full
         * /flash/... path on persistent flash storage.
         */
        $profileDirectory =
            str_starts_with(
                $directory,
                'flash/'
            )
                ? '/' . $directory
                : $directory;

        $setProfile =
            (new Query(
                '/ip/hotspot/profile/set'
            ))
                ->equal(
                    '.id',
                    (string)
                    $profile['.id']
                )
                ->equal(
                    'html-directory',
                    $profileDirectory
                )
                ->equal(
                    'login-by',
                    'http-pap,cookie'
                );

        $client
            ->query(
                $setProfile
            )
            ->read();

        $this->ensureWalledGarden(
            $client,
            $router,
            $portalHost
        );

        $verified = [];

        foreach (
            array_keys($files)
            as $name
        ) {
            $path =
                $directory
                . '/'
                . $name;

            $row =
                $this->findFile(
                    $client,
                    $path
                );

            if (!$row) {
                throw new RuntimeException(
                    "MikroTik captive file verification failed: {$path}"
                );
            }

            $verified[] =
                $path;
        }

        return [
            'success' =>
                true,

            'portal_url' =>
                $portalUrl,

            'portal_host' =>
                $portalHost,

            'hotspot_server' =>
                (string)
                $server['name'],

            'server_profile' =>
                $serverProfile,

            'directory' =>
                $directory,

            'files' =>
                $verified,
        ];
    }

    private function hotspotServer(
        Client $client,
        HotelRouter $router
    ): array {
        $query =
            (new Query(
                '/ip/hotspot/print'
            ))
                ->where(
                    'name',
                    $router
                        ->hotspot_server_name
                );

        $rows =
            $client
                ->query($query)
                ->read();

        if (
            !isset($rows[0])
            || !is_array(
                $rows[0]
            )
        ) {
            throw new RuntimeException(
                'Configured Hotel HotSpot server "'
                . $router
                    ->hotspot_server_name
                . '" was not found on MikroTik.'
            );
        }

        return $rows[0];
    }

    private function hotspotProfile(
        Client $client,
        string $profileName
    ): array {
        $query =
            (new Query(
                '/ip/hotspot/profile/print'
            ))
                ->where(
                    'name',
                    $profileName
                );

        $rows =
            $client
                ->query($query)
                ->read();

        if (
            !isset($rows[0]['.id'])
        ) {
            throw new RuntimeException(
                'HotSpot server profile "'
                . $profileName
                . '" was not found.'
            );
        }

        return $rows[0];
    }

    private function portalDirectory(
        Client $client,
        HotelRouter $router
    ): string {
        $flash =
            $this->findFile(
                $client,
                'flash'
            );

        $name =
            'mikropanel-hotel-'
            . $router->id;

        /*
         * RouterOS devices exposing /flash must keep
         * persistent custom portal files inside flash.
         */
        return $flash
            ? 'flash/' . $name
            : $name;
    }

    private function ensureDirectory(
        Client $client,
        string $directory
    ): void {
        if (
            $this->findFile(
                $client,
                $directory
            )
        ) {
            return;
        }

        $query =
            (new Query(
                '/file/add'
            ))
                ->equal(
                    'name',
                    str_starts_with(
                        $directory,
                        'flash/'
                    )
                        ? '/' . $directory
                        : $directory
                )
                ->equal(
                    'type',
                    'directory'
                );

        $client
            ->query($query)
            ->read();

        if (
            !$this->findFile(
                $client,
                $directory
            )
        ) {
            throw new RuntimeException(
                'Unable to create MikroTik portal directory.'
            );
        }
    }

    private function putFile(
        Client $client,
        string $name,
        string $contents
    ): void {
        $existing =
            $this->findFile(
                $client,
                $name
            );

        if ($existing) {
            if (
                !isset(
                    $existing['.id']
                )
            ) {
                throw new RuntimeException(
                    "Unable to update MikroTik file {$name}."
                );
            }

            $query =
                (new Query(
                    '/file/set'
                ))
                    ->equal(
                        '.id',
                        (string)
                        $existing['.id']
                    )
                    ->equal(
                        'contents',
                        $contents
                    );

            $client
                ->query($query)
                ->read();

            return;
        }

        $query =
            (new Query(
                '/file/add'
            ))
                ->equal(
                    'name',
                    str_starts_with(
                        $name,
                        'flash/'
                    )
                        ? '/' . $name
                        : $name
                )
                ->equal(
                    'type',
                    'file'
                )
                ->equal(
                    'contents',
                    $contents
                );

        $client
            ->query($query)
            ->read();
    }

    private function findFile(
        Client $client,
        string $name
    ): ?array {
        $query =
            (new Query(
                '/file/print'
            ))
                ->where(
                    'name',
                    $name
                );

        $rows =
            $client
                ->query($query)
                ->read();

        return isset($rows[0])
            && is_array($rows[0])
                ? $rows[0]
                : null;
    }

    private function ensureWalledGarden(
        Client $client,
        HotelRouter $router,
        string $host
    ): void {
        $query =
            (new Query(
                '/ip/hotspot/walled-garden/print'
            ))
                ->where(
                    'dst-host',
                    $host
                );

        $rows =
            $client
                ->query($query)
                ->read();

        foreach (
            $rows
            as $row
        ) {
            $server =
                (string) (
                    $row['server']
                    ?? ''
                );

            if (
                $server === ''
                || $server
                    === 'all'
                || $server
                    === $router
                        ->hotspot_server_name
            ) {
                return;
            }
        }

        $add =
            (new Query(
                '/ip/hotspot/walled-garden/add'
            ))
                ->equal(
                    'server',
                    $router
                        ->hotspot_server_name
                )
                ->equal(
                    'dst-host',
                    $host
                )
                ->equal(
                    'action',
                    'allow'
                )
                ->equal(
                    'comment',
                    'MikroPanel Hotel Portal #'
                    . $router->id
                );

        $client
            ->query($add)
            ->read();
    }

    private function files(
        string $portalUrl,
        int $routerId,
        string $hotelName
    ): array {
        $safePortal =
            htmlspecialchars(
                $portalUrl,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            );

        $safeHotel =
            htmlspecialchars(
                $hotelName,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            );

        $login =
            <<<HTML
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$safeHotel} WiFi</title>
<style>
html,body{margin:0;min-height:100%;background:#07111f;color:#fff;font-family:Arial,sans-serif}
.wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
.card{width:min(420px,100%);text-align:center}
.logo{width:64px;height:64px;border-radius:20px;margin:0 auto 18px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#2563eb,#06b6d4);font-size:25px;font-weight:800}
.spin{width:34px;height:34px;border:3px solid #294057;border-top-color:#38bdf8;border-radius:50%;margin:24px auto;animation:s .8s linear infinite}
@keyframes s{to{transform:rotate(360deg)}}
small{color:#94a3b8}
</style>
</head>
<body>
<div class="wrap">
<div class="card">
<div class="logo">WiFi</div>
<h2>{$safeHotel}</h2>
<p>Opening secure guest WiFi portal…</p>
<div class="spin"></div>
<small>Powered by MikroPanel Hotel Hotspot</small>
</div>
</div>

<form
    id="portal"
    method="get"
    action="{$safePortal}"
>
<input type="hidden" name="mp_router" value="{$routerId}">
<input type="hidden" name="mp_mac" value="\$(mac)">
<input type="hidden" name="mp_ip" value="\$(ip)">
</form>

<script>
document.getElementById('portal').submit();
</script>
</body>
</html>
HTML;

        $connect =
            <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Connecting WiFi</title>
<style>
html,body{margin:0;min-height:100%;background:#07111f;color:#fff;font-family:Arial,sans-serif}
.wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;text-align:center}
.card{width:min(420px,100%)}
.spin{width:42px;height:42px;border:4px solid #294057;border-top-color:#38bdf8;border-radius:50%;margin:22px auto;animation:s .8s linear infinite}
@keyframes s{to{transform:rotate(360deg)}}
.err{display:none;color:#fecaca}
</style>
</head>
<body>
<div class="wrap">
<div class="card">
<h2>Connecting to WiFi…</h2>
<div class="spin"></div>
<p>Please keep this window open.</p>
<p id="error" class="err">Invalid voucher code.</p>
</div>
</div>

<form
    id="login"
    method="post"
    action="$(link-login-only)"
>
<input type="hidden" name="username" id="username">
<input type="hidden" name="password" id="password">
<input type="hidden" name="dst" value="$(link-status)">
<input type="hidden" name="popup" value="true">
</form>

<script>
(function () {
    var params =
        new URLSearchParams(
            window.location.search
        );

    var code =
        (params.get('voucher') || '')
            .trim()
            .toUpperCase();

    if (
        !/^[A-Z0-9]{4,32}$/.test(code)
    ) {
        document
            .getElementById('error')
            .style.display = 'block';
        return;
    }

    document
        .getElementById('username')
        .value = code;

    document
        .getElementById('password')
        .value = code;

    document
        .getElementById('login')
        .submit();
})();
</script>
</body>
</html>
HTML;

        $status =
            <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WiFi Connected</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:linear-gradient(145deg,#07111f,#0f2340);font-family:Arial,sans-serif;color:#e5eef9}
.wrap{min-height:100vh;padding:22px;display:flex;align-items:center;justify-content:center}
.card{width:min(520px,100%);background:#fff;color:#0f172a;border-radius:28px;padding:26px;box-shadow:0 25px 70px rgba(0,0,0,.3)}
.badge{display:inline-flex;align-items:center;gap:8px;background:#dcfce7;color:#166534;border-radius:999px;padding:8px 13px;font-weight:700}
.dot{width:9px;height:9px;background:#22c55e;border-radius:50%}
h1{margin:18px 0 4px;font-size:28px}
.sub{color:#64748b}
.grid{margin-top:22px;display:grid;grid-template-columns:1fr 1fr;gap:10px}
.item{padding:13px;border:1px solid #e2e8f0;border-radius:16px}
.label{font-size:11px;color:#64748b;text-transform:uppercase;font-weight:700}
.value{margin-top:5px;font-weight:800;overflow-wrap:anywhere}
.btn{display:block;margin-top:20px;padding:14px;text-align:center;border-radius:14px;background:#0f172a;color:#fff;text-decoration:none;font-weight:800}
@media(max-width:480px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
<div class="card">
<div class="badge"><span class="dot"></span> WiFi Connected</div>
<h1>Internet access is active</h1>
<div class="sub">Your device is successfully authenticated.</div>

<div class="grid">
<div class="item">
<div class="label">Voucher</div>
<div class="value">$(username)</div>
</div>

<div class="item">
<div class="label">IP Address</div>
<div class="value">$(ip)</div>
</div>

<div class="item">
<div class="label">MAC Address</div>
<div class="value">$(mac)</div>
</div>

<div class="item">
<div class="label">Uptime</div>
<div class="value">$(uptime)</div>
</div>

<div class="item">
<div class="label">Downloaded</div>
<div class="value">$(bytes-out-nice)</div>
</div>

<div class="item">
<div class="label">Uploaded</div>
<div class="value">$(bytes-in-nice)</div>
</div>

<div class="item">
<div class="label">Time Remaining</div>
<div class="value">$(session-time-left)</div>
</div>

<div class="item">
<div class="label">HotSpot</div>
<div class="value">$(server-name)</div>
</div>
</div>

<a class="btn" href="$(link-logout)">
Disconnect WiFi
</a>
</div>
</div>
</body>
</html>
HTML;

        $logout =
            <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WiFi Disconnected</title>
<style>
body{margin:0;background:#07111f;color:white;font-family:Arial,sans-serif}
.wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;text-align:center}
.card{max-width:420px}
a{display:inline-block;margin-top:20px;padding:13px 20px;background:#2563eb;color:white;border-radius:14px;text-decoration:none;font-weight:700}
</style>
</head>
<body>
<div class="wrap">
<div class="card">
<h1>WiFi disconnected</h1>
<p>Your guest WiFi session has ended.</p>
<a href="$(link-login)">Connect Again</a>
</div>
</div>
</body>
</html>
HTML;

        $alogin =
            <<<'HTML'
<!doctype html>
<html>
<head>
<meta http-equiv="refresh" content="0; url=$(link-status)">
</head>
<body>
<script>
location.replace('$(link-status)');
</script>
</body>
</html>
HTML;

        $redirect =
            <<<'HTML'
<!doctype html>
<html>
<head>
<meta http-equiv="refresh" content="0; url=$(link-login)">
</head>
<body>
<script>
location.replace('$(link-login)');
</script>
</body>
</html>
HTML;

        $error =
            <<<'HTML'
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>WiFi Error</title>
</head>
<body>
<h2>Unable to connect</h2>
<p>$(error)</p>
<a href="$(link-login)">Return to WiFi login</a>
</body>
</html>
HTML;

        $api =
            <<<'JSON'
{
  "captive": $(if logged-in == 'yes')false$(else)true$(endif),
  "user-portal-url": "$(link-login-only)",
  $(if session-timeout-secs != 0)
  "seconds-remaining": $(session-timeout-secs),
  $(endif)
  $(if remain-bytes-total)
  "bytes-remaining": $(remain-bytes-total),
  $(endif)
  "can-extend-session": true
}
JSON;

        return [
            'login.html' =>
                $login,

            'flogin.html' =>
                $login,

            'rlogin.html' =>
                $login,

            'connect.html' =>
                $connect,

            'status.html' =>
                $status,

            'logout.html' =>
                $logout,

            'alogin.html' =>
                $alogin,

            'redirect.html' =>
                $redirect,

            'error.html' =>
                $error,

            'api.json' =>
                $api,
        ];
    }

    private function client(
        HotelRouter $router
    ): Client {
        return new Client(
            new Config([
                'host' =>
                    $router->host,

                'user' =>
                    $router->username,

                'pass' =>
                    $router->password,

                'port' =>
                    $router->api_port,

                'ssl' =>
                    (bool)
                    $router->use_ssl,

                'timeout' =>
                    8,

                'socket_timeout' =>
                    8,

                'attempts' =>
                    1,

                'delay' =>
                    0,
            ])
        );
    }
}
