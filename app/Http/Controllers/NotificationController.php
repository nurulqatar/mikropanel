<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $notifications = $request
            ->user()
            ->notifications()
            ->latest()
            ->paginate(25)
            ->through(
                fn ($item): array =>
                    $this->serialize(
                        $item
                    )
            );

        return Inertia::render(
            'Notifications/Index',
            [
                'notifications' =>
                    $notifications,
            ]
        );
    }

    public function read(
        Request $request,
        string $notification
    ): RedirectResponse {
        $item = $request
            ->user()
            ->notifications()
            ->whereKey(
                $notification
            )
            ->firstOrFail();

        if (!$item->read_at) {
            $item->markAsRead();
        }

        return back();
    }

    public function readAll(
        Request $request
    ): RedirectResponse {
        $request
            ->user()
            ->unreadNotifications()
            ->update([
                'read_at' => now(),
            ]);

        return back();
    }

    public function clearRead(
        Request $request
    ): RedirectResponse {
        $deleted = $request
            ->user()
            ->notifications()
            ->whereNotNull(
                'read_at'
            )
            ->delete();

        return back()->with(
            'success',
            $deleted
                . ' read notification(s) cleared.'
        );
    }

    private function serialize(
        $item
    ): array {
        $data = is_array(
            $item->data
        )
            ? $item->data
            : [];

        $clientId = isset(
            $data['client_id']
        )
            ? (int) $data['client_id']
            : null;

        return [
            'id' => $item->id,

            'kind' =>
                $data['kind']
                ?? 'info',

            'title' =>
                $data['title']
                ?? 'Notification',

            'message' =>
                $data['message']
                ?? '',

            'client_id' =>
                $clientId,

            'invoice_id' =>
                $data['invoice_id']
                ?? null,

            'payment_id' =>
                $data['payment_id']
                ?? null,

            'action_url' =>
                $clientId
                    ? route(
                        'clients.show',
                        [
                            'client' =>
                                $clientId,
                        ],
                        false
                    )
                    : null,

            'read' =>
                $item->read_at
                !== null,

            'created_at' =>
                $item
                    ->created_at
                    ?->timezone(
                        'Asia/Qatar'
                    )
                    ->format(
                        'Y-m-d H:i'
                    ),
        ];
    }
}
