<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\SaveUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserService $userService) {}

    public function index(): Response
    {
        $userAccounts = User::query()
            ->with('stores')
            ->orderByDesc('is_super_admin')
            ->orderBy('name')
            ->get();

        return Inertia::render('Central/Users/Index', [
            'users' => UserResource::collection($userAccounts)->resolve(),
            'create_url' => route('central.users.create'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Central/Users/Form', [
            'user' => null,
            'form_action' => ['url' => route('central.users.store'), 'method' => 'post'],
            ...$this->formOptions(),
        ]);
    }

    public function store(SaveUserRequest $request): RedirectResponse
    {
        $this->userService->create($this->userAttributes($request));

        return redirect()->route('central.users.index')->with('success', 'User ditambahkan.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Central/Users/Form', [
            'user' => UserResource::make($user->load(['stores', 'permissions']))->resolve(),
            'form_action' => ['url' => route('central.users.update', $user), 'method' => 'put'],
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $this->userService->update($user, $this->userAttributes($request));

        return redirect()->route('central.users.index')->with('success', 'User disimpan.');
    }

    /**
     * @return array{roles: list<array{value: string, label: string, is_store_role: bool}>, stores: list<array{id: int, name: string}>, permission_groups: list<array{group: string, permissions: list<array{name: string, label: string}>}>}
     */
    private function formOptions(): array
    {
        return [
            'roles' => Role::query()->orderBy('id')->get()->prepend(Role::superAdmin())
                ->map(fn (Role $role): array => ['value' => $role->name, 'label' => $role->label, 'is_store_role' => ! $role->isSuperAdmin()])
                ->all(),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name'])->toArray(),
            'permission_groups' => Permission::groupedForForm(),
        ];
    }

    /**
     * @return array{name: string, username: string, password: string|null, role: string, store_id: int|null, permissions: list<string>, is_active: bool}
     */
    private function userAttributes(SaveUserRequest $request): array
    {
        return [
            'name' => $request->string('name')->toString(),
            'username' => $request->string('username')->toString(),
            'password' => $request->input('password'),
            'role' => $request->string('role')->toString(),
            'store_id' => $request->integer('store_id') ?: null,
            'permissions' => $request->input('permissions', []),
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
