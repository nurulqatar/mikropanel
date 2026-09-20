<?php

namespace App\Http\Middleware\Compliance;

use App\Models\Compliance\Collector;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateComplianceCollector
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = trim((string) $request->bearerToken());
        $uuid = trim((string) $request->header('X-Collector-UUID'));

        if ($token === '' || $uuid === '') {
            return new JsonResponse([
                'message' => 'Collector authentication required.',
            ], 401);
        }

        $collector = Collector::query()
            ->where('uuid', $uuid)
            ->where('token_hash', hash('sha256', $token))
            ->where('status', 'active')
            ->whereNull('revoked_at')
            ->first();

        if (!$collector) {
            return new JsonResponse([
                'message' => 'Invalid or revoked collector.',
            ], 401);
        }

        $request->attributes->set('complianceCollector', $collector);
        return $next($request);
    }
}
