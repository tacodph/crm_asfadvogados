<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TenantUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_tenant_users_page(): void
    {
        $owner = User::factory()->create(['role' => Role::OWNER]);

        $this->actingAs($owner)
            ->withoutVite()
            ->get(route('settings.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Users')
                ->has('users', 1)
                ->has('roles'));
    }

    public function test_member_cannot_manage_tenant_users(): void
    {
        $member = User::factory()->create(['role' => Role::MEMBER]);

        $this->actingAs($member)
            ->get(route('settings.users.index'))
            ->assertForbidden();
    }

    public function test_owner_can_create_a_tenant_user(): void
    {
        $owner = User::factory()->create(['role' => Role::OWNER]);

        $this->actingAs($owner)
            ->post(route('settings.users.store'), [
                'name' => 'Ana Souza',
                'email' => 'ana.souza@escritorio.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => Role::MEMBER,
                'especialidades' => 'alimentos, saude',
                'ausente_ate' => null,
            ])
            ->assertRedirect(route('settings.users.index'));

        $created = User::query()->where('email', 'ana.souza@escritorio.test')->first();

        $this->assertNotNull($created);
        $this->assertSame($owner->tenant_id, $created->tenant_id);
        $this->assertSame(Role::MEMBER, $created->role);
        $this->assertSame(['alimentos', 'saude'], $created->especialidades);
    }

    public function test_admin_cannot_create_an_owner(): void
    {
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $this->actingAs($admin)
            ->post(route('settings.users.store'), [
                'name' => 'Outro Owner',
                'email' => 'outro.owner@escritorio.test',
                'password' => 'password',
                'password_confirmation' => 'password',
                'role' => Role::OWNER,
            ])
            ->assertSessionHasErrors('role');

        $this->assertFalse(
            User::query()->where('email', 'outro.owner@escritorio.test')->exists(),
        );
    }

    public function test_owner_can_update_a_tenant_user(): void
    {
        $owner = User::factory()->create(['role' => Role::OWNER]);
        $member = User::factory()->create([
            'role' => Role::MEMBER,
            'name' => 'Membro Antigo',
        ]);

        $this->actingAs($owner)
            ->patch(route('settings.users.update', $member), [
                'name' => 'Membro Novo',
                'email' => $member->email,
                'role' => Role::ADMIN,
                'especialidades' => 'software-b2b',
                'ausente_ate' => '2026-09-01',
            ])
            ->assertRedirect(route('settings.users.index'));

        $member->refresh();

        $this->assertSame('Membro Novo', $member->name);
        $this->assertSame(Role::ADMIN, $member->role);
        $this->assertSame(['software-b2b'], $member->especialidades);
        $this->assertSame('2026-09-01', $member->ausente_ate?->toDateString());
    }

    public function test_cannot_manage_users_from_another_tenant(): void
    {
        $owner = User::factory()->create(['role' => Role::OWNER]);
        $otherTenant = Tenant::factory()->create();

        $foreign = app(CurrentTenant::class)->runAs(
            $otherTenant,
            fn (): User => User::factory()->create([
                'tenant_id' => $otherTenant->id,
                'role' => Role::MEMBER,
            ]),
        );

        $this->actingAs($owner)
            ->get(route('settings.users.edit', $foreign))
            ->assertNotFound();
    }

    public function test_cannot_remove_the_last_owner(): void
    {
        $owner = User::factory()->create(['role' => Role::OWNER]);
        $admin = User::factory()->create(['role' => Role::ADMIN]);

        $this->actingAs($admin)
            ->from(route('settings.users.index'))
            ->delete(route('settings.users.destroy', $owner))
            ->assertRedirect()
            ->assertSessionHasErrors('user');

        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_owner_cannot_remove_themselves(): void
    {
        $owner = User::factory()->create(['role' => Role::OWNER]);
        User::factory()->create(['role' => Role::OWNER]);

        $this->actingAs($owner)
            ->from(route('settings.users.index'))
            ->delete(route('settings.users.destroy', $owner))
            ->assertRedirect()
            ->assertSessionHasErrors('user');
    }
}
