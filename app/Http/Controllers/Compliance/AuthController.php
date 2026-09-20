<?php

namespace App\Http\Controllers\Compliance;

use App\Http\Controllers\Controller;
use App\Models\Compliance\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(
        Request $request
    ): Response|RedirectResponse {
        if (
            $request
                ->session()
                ->has(
                    'compliance_user_id'
                )
        ) {
            return redirect()
                ->route(
                    'compliance.dashboard'
                );
        }

        return Inertia::render(
            'Compliance/Auth/Login'
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $data =
            $request->validate([
                'email' => [
                    'required',
                    'email',
                    'max:255',
                ],

                'password' => [
                    'required',
                    'string',
                ],
            ]);

        $email =
            mb_strtolower(
                trim(
                    $data['email']
                )
            );

        $user =
            User::query()
                ->with('organization')
                ->where(
                    'email',
                    $email
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if (
            !$user
            || !Hash::check(
                $data['password'],
                $user->password
            )
            || !$user->organization
            || $user->organization->status
                !== 'active'
        ) {
            return back()
                ->withErrors([
                    'email' =>
                        'Invalid or inactive Compliance account.',
                ])
                ->onlyInput('email');
        }

        $request
            ->session()
            ->regenerate();

        /* COMPLIANCE_STANDALONE_MASK_CLEAR_V1 */
        $request->session()->forget([
            'compliance_service_mask',
            'compliance_bridge_source',
        ]);

        $request
            ->session()
            ->put([
                'compliance_user_id' =>
                    $user->id,

                'compliance_organization_id'
                    => $user
                        ->organization_id,
            ]);

        $user->forceFill([
            'last_login_at' =>
                now(),
        ])->save();

        DB::table(
            'compliance_audit_logs'
        )->insert([
            'organization_id' =>
                $user->organization_id,

            'compliance_user_id' =>
                $user->id,

            'panel_user_id' =>
                null,

            'action' =>
                'auth.login',

            'subject_type' =>
                'compliance_user',

            'subject_id' =>
                (string) $user->id,

            'ip_address' =>
                $request->ip(),

            'metadata' =>
                json_encode([
                    'portal' =>
                        'standalone',
                ]),

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);

        return redirect()
            ->route(
                'compliance.dashboard'
            );
    }

    public function destroy(
        Request $request
    ): RedirectResponse {
        $request->session()->forget([
            'compliance_user_id',
            'compliance_organization_id',
            'compliance_service_mask',
            'compliance_bridge_source',
        ]);

        $request
            ->session()
            ->forget([
                'compliance_user_id',
                'compliance_organization_id',
            ]);

        $request
            ->session()
            ->regenerateToken();

        return redirect()
            ->route(
                'compliance.login'
            );
    }
}
