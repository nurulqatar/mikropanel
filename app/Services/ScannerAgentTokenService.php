<?php

namespace App\Services;

use RuntimeException;

final class ScannerAgentTokenService
{
    public function issue(
        string $origin,
        string|int $subject,
    ): array {
        $origin = rtrim(
            trim($origin),
            '/',
        );

        if (
            ! preg_match(
                '#^https?://[^/]+$#i',
                $origin,
            )
        ) {
            throw new RuntimeException(
                'Invalid scanner token origin.',
            );
        }

        $now = time();

        $ttl = max(
            60,
            min(
                600,
                (int) config(
                    'scanner_agent.token_ttl_seconds',
                    300,
                ),
            ),
        );

        $expiresAt =
            $now + $ttl;

        $payload = [
            'iss' => 'mikropanel',
            'aud' => 'scanner-agent',

            'sub' => (string) $subject,

            /*
             * Current browser origin.
             *
             * If MikroPanel later moves from an IP
             * to a domain, or from one domain to
             * another, the new origin simply gets
             * a new valid signed token.
             */
            'origin' => $origin,

            'iat' => $now,
            'exp' => $expiresAt,

            'nonce' => bin2hex(
                random_bytes(16),
            ),
        ];

        $payloadJson = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR,
        );

        $encodedPayload =
            $this->base64UrlEncode(
                $payloadJson,
            );

        $privateKeyPath =
            (string) config(
                'scanner_agent.private_key_path',
            );

        $privatePem =
            @file_get_contents(
                $privateKeyPath,
            );

        if (
            $privatePem === false
            || trim($privatePem) === ''
        ) {
            throw new RuntimeException(
                'Scanner signing key is unavailable.',
            );
        }

        $privateKey =
            openssl_pkey_get_private(
                $privatePem,
            );

        if ($privateKey === false) {
            throw new RuntimeException(
                'Scanner signing key is invalid.',
            );
        }

        $signature = '';

        if (
            ! openssl_sign(
                $encodedPayload,
                $signature,
                $privateKey,
                OPENSSL_ALGO_SHA256,
            )
        ) {
            throw new RuntimeException(
                'Scanner token signing failed.',
            );
        }

        $token =
            $encodedPayload
            . '.'
            . $this->base64UrlEncode(
                $signature,
            );

        return [
            'token' => $token,
            'expires_at' => $expiresAt,
            'origin' => $origin,
        ];
    }

    private function base64UrlEncode(
        string $value,
    ): string {
        return rtrim(
            strtr(
                base64_encode($value),
                '+/',
                '-_',
            ),
            '=',
        );
    }
}
