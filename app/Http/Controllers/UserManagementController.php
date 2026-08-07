<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(
        Request $request
    ): Response {
        $this->authorizeAdmin($request);

        return Inertia::render(
            'Users/Index',
            [
                'users' => User::query()
                    ->orderByDesc('id')
                    ->get([
                        'id',
                        'name',
                        'email',
                        'role',
                        'permissions',
                        'is_active',
                        'created_at',
                    ]),

                'currentUserId' =>
                    $request->user()->id,
            ]
        );
    }

    public function create(
        Request $request
    ): Response {
        $this->authorizeAdmin($request);

        return Inertia::render(
            'Users/Create',
            [
                'permissionGroups' =>
                    config(
                        'panel_permissions.groups',
                        []
                    ),
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        $data = $this->validateUser(
            $request
        );

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],

            'email_verified_at' =>
                now(),

            'password' =>
                $data['password'],

            'role' => $data['role'],

            'permissions' =>
                $data['role'] === 'admin'
                    ? []
                    : array_values(
                        array_unique(
                            $data['permissions']
                            ?? []
                        )
                    ),

            'is_active' =>
                (bool) $data['is_active'],
        ]);

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'Panel user created successfully.'
            );
    }

    public function edit(
        Request $request,
        User $user
    ): Response {
        $this->authorizeAdmin($request);

        return Inertia::render(
            'Users/Edit',
            [
                'panelUser' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,

                    'permissions' =>
                        $user->permissions
                        ?? [],

                    'is_active' =>
                        (bool) $user->is_active,
                ],

                'permissionGroups' =>
                    config(
                        'panel_permissions.groups',
                        []
                    ),

                'currentUserId' =>
                    $request->user()->id,
            ]
        );
    }

    public function update(
        Request $request,
        User $user
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        $data = $this->validateUser(
            $request,
            $user
        );

        $isOwnAccount =
            $request->user()->is($user);

        if (
            $isOwnAccount
            && $data['role'] !== 'admin'
        ) {
            throw ValidationException::withMessages([
                'role' =>
                    'You cannot remove your own admin role.',
            ]);
        }

        if (
            $isOwnAccount
            && !$data['is_active']
        ) {
            throw ValidationException::withMessages([
                'is_active' =>
                    'You cannot disable your own account.',
            ]);
        }

        if (
            $user->isAdmin()
            && $data['role'] !== 'admin'
            && $this->adminCount() <= 1
        ) {
            throw ValidationException::withMessages([
                'role' =>
                    'The last admin cannot be changed to operator.',
            ]);
        }

        if (
            $user->isAdmin()
            && !$data['is_active']
            && $this->activeAdminCount() <= 1
        ) {
            throw ValidationException::withMessages([
                'is_active' =>
                    'The last active admin cannot be disabled.',
            ]);
        }

        $values = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],

            'permissions' =>
                $data['role'] === 'admin'
                    ? []
                    : array_values(
                        array_unique(
                            $data['permissions']
                            ?? []
                        )
                    ),

            'is_active' =>
                (bool) $data['is_active'],
        ];

        if (filled($data['password'] ?? null)) {
            $values['password'] =
                $data['password'];
        }

        $user->update($values);

        return redirect()
            ->route('users.index')
            ->with(
                'success',
                'Panel user updated successfully.'
            );
    }

    public function destroy(
        Request $request,
        User $user
    ): RedirectResponse {
        $this->authorizeAdmin($request);

        if ($request->user()->is($user)) {
            return back()->withErrors([
                'user' =>
                    'You cannot delete your own account.',
            ]);
        }

        if (
            $user->isAdmin()
            && $this->adminCount() <= 1
        ) {
            return back()->withErrors([
                'user' =>
                    'The last admin cannot be deleted.',
            ]);
        }

        $user->delete();

        return back()->with(
            'success',
            'Panel user deleted successfully.'
        );
    }

    private function validateUser(
        Request $request,
        ?User $user = null
    ): array {
        $passwordRules = $user
            ? [
                'nullable',
                'string',
                'min:8',
                'confirmed',
            ]
            : [
                'required',
                'string',
                'min:8',
                'confirmed',
            ];

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'email' => [
                'required',
                'email',
                'max:255',

                Rule::unique(
                    'users',
                    'email'
                )->ignore($user?->id),
            ],

            'role' => [
                'required',
                Rule::in([
                    'admin',
                    'operator',
                ]),
            ],

            'permissions' => [
                'nullable',
                'array',
            ],

            'permissions.*' => [
                'string',

                Rule::in(
                    $this->permissionKeys()
                ),
            ],

            'is_active' => [
                'required',
                'boolean',
            ],

            'password' =>
                $passwordRules,
        ]);
    }

    private function permissionKeys(): array
    {
        return collect(
            config(
                'panel_permissions.groups',
                []
            )
        )
            ->flatMap(
                fn (array $group): array =>
                    array_keys(
                        $group['permissions']
                        ?? []
                    )
            )
            ->values()
            ->all();
    }

    private function authorizeAdmin(
        Request $request
    ): void {
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'Only an administrator can manage panel users.'
        );
    }

    private function adminCount(): int
    {
        return User::query()
            ->where('role', 'admin')
            ->count();
    }

    private function activeAdminCount(): int
    {
        return User::query()
            ->where('role', 'admin')
            ->where('is_active', true)
            ->count();
    }
}
