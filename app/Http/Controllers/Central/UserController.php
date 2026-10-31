<?php

namespace App\Http\Controllers\Central;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\SaveUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Store;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(): Response
    {
        $users = User::query()
            ->with('stores')
            ->orderByDesc('is_super_admin')
            ->orderBy('name')
            ->get();

        return Inertia::render('Central/Users/Index', [
            'users' => UserResource::collection($users)->resolve(),
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
        $this->users->create($this->userData($request));

        return redirect()->route('central.users.index')->with('success', 'User ditambahkan.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Central/Users/Form', [
            'user' => UserResource::make($user->load('stores'))->resolve(),
            'form_action' => ['url' => route('central.users.update', $user), 'method' => 'put'],
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveUserRequest $request, User $user): RedirectResponse
    {
        $this->users->update($user, $this->userData($request));

        return redirect()->route('central.users.index')->with('success', 'User disimpan.');
    }

    /**
     * @return array{roles: list<array{value: string, label: string}>, stores: list<array{id: int, name: string}>}
     */
    private function formOptions(): array
    {
        return [
            'roles' => UserRole::options(),
            'stores' => Store::query()->orderBy('name')->get(['id', 'name'])->toArray(),
        ];
    }

    /**
     * @return array{name: string, username: string, password: string|null, role: UserRole, store_id: int|null, is_active: bool}
     */
    private function userData(SaveUserRequest $request): array
    {
        return [
            'name' => $request->string('name')->toString(),
            'username' => $request->string('username')->toString(),
            'password' => $request->input('password'),
            'role' => $request->enum('role', UserRole::class),
            'store_id' => $request->integer('store_id') ?: null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
