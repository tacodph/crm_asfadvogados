<?php

namespace Tests\Feature;

use App\Models\Contato;
use App\Models\Empresa;
use App\Models\Negociacao;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CrmCountsScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_crm_counts_only_reflect_the_current_tenant(): void
    {
        $outro = $this->createTenant();
        $this->asTenant($outro, function () {
            Empresa::factory()->count(3)->create();
            Contato::factory()->count(5)->create();
            Negociacao::factory()->count(2)->create();
        });

        Empresa::factory()->create();
        // pessoaFisica() avoids the factory's default empresa_id, which
        // would otherwise implicitly create a second Empresa via its
        // nested Empresa::factory() default.
        Contato::factory()->pessoaFisica()->create();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('crmCounts.empresas', '1')
                ->where('crmCounts.contatos', '1')
                ->where('crmCounts.negociacoes', '0'));
    }

    public function test_crm_counts_are_null_for_guests(): void
    {
        $this->get($this->centralUrl('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('crmCounts', null));
    }
}
