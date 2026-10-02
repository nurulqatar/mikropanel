<?php

namespace App\Services\Compliance;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class ComplianceS3Service
{
    public function put(array $config, string $key, string $body): string
    {
        return $this->request('PUT', $config, $key, $body);
    }

    public function delete(array $config, string $key): void
    {
        $this->request('DELETE', $config, $key, '');
    }

    private function request(
        string $method,
        array $config,
        string $key,
        string $body
    ): string {
        $accessKey = trim((string) ($config['access_key'] ?? ''));
        $secretKey = (string) ($config['secret_key'] ?? '');
        $region = trim((string) ($config['region'] ?? 'us-east-1'));
        $bucket = trim((string) ($config['bucket'] ?? ''));
        $endpoint = rtrim(trim((string) ($config['endpoint'] ?? '')), '/');
        $pathStyle = (bool) ($config['path_style'] ?? true);

        if ($accessKey === '' || $secretKey === '' || $bucket === '') {
            throw new RuntimeException(
                'S3 access key, secret key and bucket are required.'
            );
        }

        if ($endpoint === '') {
            $endpoint = 'https://s3.' . $region . '.amazonaws.com';
        }

        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('Invalid S3 endpoint.');
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new RuntimeException('S3 endpoint must use HTTP or HTTPS.');
        }

        $baseHost = (string) $parts['host'];
        if (!empty($parts['port'])) {
            $baseHost .= ':' . (int) $parts['port'];
        }

        $encodedKey = collect(explode('/', ltrim($key, '/')))
            ->map(fn ($part) => rawurlencode($part))
            ->implode('/');

        if ($pathStyle) {
            $host = $baseHost;
            $canonicalUri = '/' . rawurlencode($bucket) . '/' . $encodedKey;
        } else {
            $host = $bucket . '.' . $baseHost;
            $canonicalUri = '/' . $encodedKey;
        }

        $url = $scheme . '://' . $host . $canonicalUri;
        $now = now('UTC');
        $amzDate = $now->format('Ymd\THis\Z');
        $dateStamp = $now->format('Ymd');
        $payloadHash = hash('sha256', $body);
        $canonicalHeaders =
            'host:' . $host . "\n"
            . 'x-amz-content-sha256:' . $payloadHash . "\n"
            . 'x-amz-date:' . $amzDate . "\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';
        $canonicalRequest =
            $method . "\n"
            . $canonicalUri . "\n\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $payloadHash;
        $scope = $dateStamp . '/' . $region . '/s3/aws4_request';
        $stringToSign =
            "AWS4-HMAC-SHA256\n"
            . $amzDate . "\n"
            . $scope . "\n"
            . hash('sha256', $canonicalRequest);

        $dateKey = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
        $regionKey = hash_hmac('sha256', $region, $dateKey, true);
        $serviceKey = hash_hmac('sha256', 's3', $regionKey, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $serviceKey, true);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);
        $authorization =
            'AWS4-HMAC-SHA256 Credential=' . $accessKey . '/' . $scope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        $response = Http::timeout(20)
            ->withHeaders([
                'Authorization' => $authorization,
                'Host' => $host,
                'x-amz-content-sha256' => $payloadHash,
                'x-amz-date' => $amzDate,
                'Content-Type' => 'application/gzip',
            ])
            ->send($method, $url, ['body' => $body]);

        if (
            !$response->successful()
            && !($method === 'DELETE' && $response->status() === 404)
        ) {
            throw new RuntimeException(
                'S3 request failed with HTTP ' . $response->status() . '.'
            );
        }

        return $url;
    }
}
