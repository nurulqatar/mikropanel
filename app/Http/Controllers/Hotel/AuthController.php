<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    public function create(): Response|RedirectResponse
    {
        if (
            Auth::guard('hotel')
                ->check()
        ) {
            return redirect()
                ->route(
                    'hotel.dashboard'
                );
        }

        return Inertia::render(
            'Hotel/Auth/Login'
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
                ],

                'password' => [
                    'required',
                    'string',
                ],
            ]);

        if (
            !Auth::guard('hotel')
                ->attempt(
                    [
                        'email' =>
                            strtolower(
                                trim(
                                    $data['email']
                                )
                            ),

                        'password' =>
                            $data['password'],

                        'is_active' =>
                            true,
                    ],
                    $request->boolean(
                        'remember'
                    )
                )
        ) {
            return back()
                ->withErrors([
                    'email' =>
                        'Invalid Hotel login credentials.',
                ])
                ->onlyInput(
                    'email'
                );
        }

        $user =
            Auth::guard('hotel')
                ->user();

        if (
            !$user->hotel
            || $user
                ->hotel
                ->status
                !== 'active'
        ) {
            Auth::guard('hotel')
                ->logout();

            return back()
                ->withErrors([
                    'email' =>
                        'Hotel account is suspended or unavailable.',
                ]);
        }

        $request
            ->session()
            ->regenerate();

        $user->forceFill([
            'last_login_at' =>
                now(),
        ])->save();

        return redirect()
            ->route(
                'hotel.dashboard'
            );
    }

    public function destroy(
        Request $request
    ): RedirectResponse {
        Auth::guard('hotel')
            ->logout();

        $request
            ->session()
            ->invalidate();

        $request
            ->session()
            ->regenerateToken();

        return redirect()
            ->route(
                'hotel.login'
            );
    }
}
