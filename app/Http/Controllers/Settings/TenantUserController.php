<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreTenantUserRequest;
use App\Http\Requests\Settings\UpdateTenantUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantUserController extends Controller
{
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request->user());

        $tenantId = $request->user()->tenant_id;

        $users = User::query()
            ->with('roleModel:id,slug,nome')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id', 'especialidades', 'ausente_ate', 'created_at'])
            ->map(fn (User $user): array => $this->serialize($user))
            ->values()
            ->all();

        return Inertia::render('settings/Users', [
            'users' => $users,
            'roles' => $this->roleOptions($request->user()),
            'tenant' => [
                'name' => $request->user()->tenant?->name,
            ],
        ]);
    }

    public function store(StoreTenantUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        User::query()->create([
            'tenant_id' => $request->user()->tenant_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'especialidades' => $data['especialidades'] ?? [],
            'ausente_ate' => $data['ausente_ate'] ?? null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Usuário adicionado ao escritório.',
        ]);

        return redirect()->route('settings.users.index');
    }

    public function edit(Request $request, User $user): Response
    {
        $this->ensureCanManage($request->user());
        $this->ensureSameTenant($request->user(), $user);

        $user->loadMissing('roleModel:id,slug,nome');

        return Inertia::render('settings/UserEdit', [
            'user' => $this->serialize($user),
            'roles' => $this->roleOptions($request->user(), $user),
        ]);
    }

    public function update(UpdateTenantUserRequest $request, User $user): RedirectResponse
    {
        $this->ensureSameTenant($request->user(), $user);

        $data = $request->validated();

        if (
            $user->isOwner()
            && ($data['role'] ?? $user->role) !== Role::OWNER
            && $this->isLastOwner($user)
        ) {
            return back()->withErrors([
                'role' => 'Não é possível remover o último responsável pela conta.',
            ]);
        }

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'especialidades' => $data['especialidades'] ?? [],
            'ausente_ate' => $data['ausente_ate'] ?? null,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        }

        $user->update($payload);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Usuário atualizado.',
        ]);

        return redirect()->route('settings.users.index');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->ensureCanManage($request->user());
        $this->ensureSameTenant($request->user(), $user);

        if ($user->is($request->user())) {
            return back()->withErrors([
                'user' => 'Você não pode remover a si mesmo.',
            ]);
        }

        if ($user->isOwner() && $this->isLastOwner($user)) {
            return back()->withErrors([
                'user' => 'Não é possível remover o último responsável pela conta.',
            ]);
        }

        if ($user->negociacoes()->exists() || $user->empresas()->exists()) {
            return back()->withErrors([
                'user' => 'Não é possível remover: há negociações ou empresas vinculadas a este usuário.',
            ]);
        }

        $user->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Usuário removido.',
        ]);

        return redirect()->route('settings.users.index');
    }

    private function ensureCanManage(?User $actor): void
    {
        abort_unless($actor !== null && $actor->canManageTenantUsers(), 403);
    }

    private function ensureSameTenant(User $actor, User $user): void
    {
        abort_unless($actor->tenant_id === $user->tenant_id, 404);
    }

    private function isLastOwner(User $user): bool
    {
        return User::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('role_id', Role::idFor(Role::OWNER))
            ->whereKeyNot($user->id)
            ->doesntExist();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(User $actor, ?User $editing = null): array
    {
        Role::ensureDefaults();

        $roles = Role::query()
            ->orderBy('ordem')
            ->orderBy('nome')
            ->get();

        if (! $actor->isOwner()) {
            $roles = $roles->reject(
                fn (Role $role): bool => $role->slug === Role::OWNER
                    && $editing?->role !== Role::OWNER,
            )->values();
        }

        if (
            $editing?->isOwner()
            && ! $actor->isOwner()
        ) {
            $roles = Role::query()
                ->orderBy('ordem')
                ->orderBy('nome')
                ->get();
        }

        return $roles
            ->map(fn (Role $role): array => [
                'value' => $role->slug,
                'label' => $role->nome,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     name: string,
     *     email: string,
     *     role: string,
     *     role_label: string,
     *     especialidades: list<string>,
     *     ausente_ate: string|null,
     *     created_at: string|null
     * }
     */
    private function serialize(User $user): array
    {
        $user->loadMissing('roleModel:id,slug,nome');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role ?? Role::MEMBER,
            'role_label' => $user->roleModel?->nome ?? $user->role ?? Role::MEMBER,
            'especialidades' => $user->especialidades ?? [],
            'ausente_ate' => $user->ausente_ate?->toDateString(),
            'created_at' => $user->created_at?->toDateString(),
        ];
    }
}
