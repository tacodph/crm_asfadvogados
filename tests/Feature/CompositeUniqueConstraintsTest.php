<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Setor;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompositeUniqueConstraintsTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_globally_unique_across_tenants(): void
    {
        User::factory()->create(['email' => 'duplicado@email.com']);

        $this->expectException(QueryException::class);

        $outro = $this->createTenant();
        $this->asTenant($outro, fn () => User::factory()->create(['email' => 'duplicado@email.com']));
    }

    public function test_same_cnpj_can_belong_to_empresas_in_different_tenants(): void
    {
        $outro = $this->createTenant();
        $this->asTenant($outro, fn () => Empresa::factory()->create(['cnpj' => '11.111.111/0001-11']));

        $empresa = Empresa::factory()->create(['cnpj' => '11.111.111/0001-11']);

        $this->assertNotNull($empresa->id);
    }

    public function test_same_cnpj_cannot_belong_to_two_empresas_in_the_same_tenant(): void
    {
        Empresa::factory()->create(['cnpj' => '22.222.222/0001-22']);

        $this->expectException(QueryException::class);

        Empresa::factory()->create(['cnpj' => '22.222.222/0001-22']);
    }

    public function test_same_catalog_slug_can_exist_in_different_tenants(): void
    {
        $outro = $this->createTenant();
        $this->asTenant($outro, fn () => Setor::factory()->create(['slug' => 'juridico', 'nome' => 'Jurídico']));

        $setor = Setor::factory()->create(['slug' => 'juridico', 'nome' => 'Jurídico (outro nome)']);

        $this->assertNotNull($setor->id);
    }

    public function test_same_catalog_slug_cannot_repeat_within_the_same_tenant(): void
    {
        Setor::factory()->create(['slug' => 'financeiro', 'nome' => 'Financeiro']);

        $this->expectException(QueryException::class);

        Setor::factory()->create(['slug' => 'financeiro', 'nome' => 'Outro nome']);
    }
}
