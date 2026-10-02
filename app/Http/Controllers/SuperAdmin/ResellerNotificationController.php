<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ResellerNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ResellerNotificationController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->access(
            $request
        );

        return Inertia::render(
            'SuperAdmin/Notifications/Index',
            [
                'notifications' =>
                    ResellerNotification::query()
                        ->with(
                            'reseller:id,code,company_name'
                        )
                        ->latest('id')
                        ->limit(500)
                        ->get(),

                'unread' =>
                    ResellerNotification::query()
                        ->whereNull(
                            'read_at'
                        )
                        ->count(),
            ]
        );
    }

    public function read(
        Request $request,
        ResellerNotification $notification
    ): RedirectResponse {
        $this->access(
            $request
        );

        $notification->forceFill([
            'read_at' =>
                now(
                    'Asia/Qatar'
                ),
        ])->save();

        return back();
    }

    public function readAll(
        Request $request
    ): RedirectResponse {
        $this->access(
            $request
        );

        ResellerNotification::query()
            ->whereNull(
                'read_at'
            )
            ->update([
                'read_at' =>
                    now(
                        'Asia/Qatar'
                    ),
            ]);

        return back();
    }

    private function access(
        Request $request
    ): void {
        abort_unless(
            $request->user()
            && $request
                ->user()
                ->isSuperAdmin(),
            403
        );
    }
}
