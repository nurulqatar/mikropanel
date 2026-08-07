<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'auth' => [
                'user' => $request->user(),
            ],

            'panelNotifications' =>
                function () use ($request): array {
                    $user = $request->user();

                    if (
                        !$user
                        || !Schema::hasTable(
                            'notifications'
                        )
                    ) {
                        return [
                            'unread_count' => 0,
                            'items' => [],
                        ];
                    }

                    $items = $user
                        ->notifications()
                        ->latest()
                        ->limit(8)
                        ->get()
                        ->map(
                            function ($item): array {
                                $data =
                                    is_array(
                                        $item->data
                                    )
                                        ? $item->data
                                        : [];

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
                                        isset(
                                            $data['client_id']
                                        )
                                            ? (int)
                                                $data['client_id']
                                            : null,

                                    'action_url' =>
                                        isset(
                                            $data['client_id']
                                        )
                                            ? route(
                                                'clients.show',
                                                [
                                                    'client' =>
                                                        (int)
                                                        $data[
                                                            'client_id'
                                                        ],
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
                        )
                        ->values()
                        ->all();

                    return [
                        'unread_count' =>
                            $user
                                ->unreadNotifications()
                                ->count(),

                        'items' => $items,
                    ];
                },
        ];
    }
}
