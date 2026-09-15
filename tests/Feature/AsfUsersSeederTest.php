<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\AsfUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsfUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_asf_team_users_from_live_database(): void
    {
        $this->seed(AsfUsersSeeder::class);

        $tenant = Tenant::query()->where('slug', 'asfadvogados')->firstOrFail();

        app(CurrentTenant::class)->runAs($tenant, function (): void {
            $this->assertSame(7, User::query()->count());
            $this->assertTrue(User::query()->where('email', 'dev@asfadvogados.com.br')->exists());
            $this->assertTrue(User::query()->where('email', 'silasadauto@asfadvogados.com')->exists());
            $this->assertTrue(User::query()->where('email', 'brunogabriel@asfadvogados.com')->exists());
            $this->assertTrue(User::query()->where('email', 'flavio.augusto@asfadvogados.com')->exists());
            $this->assertTrue(User::query()->where('email', 'comercial.pj@asfadvogados.adv.br')->exists());

            $bruno = User::query()->where('email', 'brunogabriel@asfadvogados.com')->firstOrFail();
            $this->assertSame('Bruno Gabriel', $bruno->name);
            $this->assertSame(Role::ADMIN, $bruno->role);
            $this->assertSame(['concursos'], $bruno->especialidades);
        });
    }
}
