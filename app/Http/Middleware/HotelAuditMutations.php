<?php

namespace App\Http\Middleware;

use App\Models\Hotel\HotelAuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class HotelAuditMutations
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $response =
            $next($request);

        if (
            $request->isMethodSafe()
            || $response->getStatusCode()
                >= 400
            || !Schema::hasTable(
                'hotel_audit_logs'
            )
        ) {
            return $response;
        }

        $user =
            Auth::guard('hotel')
                ->user();

        if (!$user) {
            return $response;
        }

        $data =
            $request->except([
                '_token',
                '_method',
                'password',
                'password_confirmation',
            ]);

        HotelAuditLog::query()
            ->create([
                'hotel_id' =>
                    $user->hotel_id,

                'hotel_user_id' =>
                    $user->id,

                'actor_type' =>
                    $user->isAdmin()
                        ? 'hotel_admin'
                        : 'hotel_staff',

                'actor_name' =>
                    $user->name
                    ?? $user->email,

                'action' =>
                    $request
                        ->route()
                        ?->getName()
                    ?? (
                        $request->method()
                        . ' '
                        . $request->path()
                    ),

                'subject_type' =>
                    'http_request',

                'description' =>
                    'Successful Hotel panel mutation.',

                'metadata' => [
                    'method' =>
                        $request->method(),

                    'route' =>
                        $request
                            ->route()
                            ?->getName(),

                    'fields' =>
                        array_keys(
                            $data
                        ),
                ],

                'ip_address' =>
                    $request->ip(),
            ]);

        return $response;
    }
}
