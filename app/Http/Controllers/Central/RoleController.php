<?php

namespace App\Http\Controllers\Central;

use App\Exceptions\RoleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\SaveRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService) {}

    public function index(): Response
    {
        $roles = Role::query()->with('permissions')->withCount('users')->orderBy('id')->get();

        return Inertia::render('Central/Roles/Index', [
            'roles' => RoleResource::collection($roles)->resolve(),
            'permission_count' => Permission::query()->count(),
            'create_url' => route('central.roles.create'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Central/Roles/Form', [
            'role' => null,
            'form_action' => ['url' => route('central.roles.store'), 'method' => 'post'],
            'permission_groups' => Permission::groupedForForm(),
        ]);
    }

    public function store(SaveRoleRequest $request): RedirectResponse
    {
        $this->roleService->create($this->roleAttributes($request));

        return redirect()->route('central.roles.index')->with('success', 'Peran ditambahkan.');
    }

    public function edit(Role $role): Response
    {
        return Inertia::render('Central/Roles/Form', [
            'role' => RoleResource::make($role->load('permissions')->loadCount('users'))->resolve(),
            'form_action' => ['url' => route('central.roles.update', $role), 'method' => 'put'],
            'permission_groups' => Permission::groupedForForm(),
        ]);
    }

    public function update(SaveRoleRequest $request, Role $role): RedirectResponse
    {
        $this->roleService->update($role, $this->roleAttributes($request));

        return redirect()->route('central.roles.index')->with('success', 'Peran disimpan.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        try {
            $this->roleService->delete($role);
        } catch (RoleException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('central.roles.index')->with('success', 'Peran dihapus.');
    }

    /**
     * @return array{label: string, permissions: list<string>}
     */
    private function roleAttributes(SaveRoleRequest $request): array
    {
        return [
            'label' => $request->string('label')->toString(),
            'permissions' => $request->input('permissions', []),
        ];
    }
}
