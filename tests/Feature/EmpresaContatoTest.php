<?php

namespace Tests\Feature;

use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\Setor;
use App\Models\StatusConflito;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use App\Models\Uf;
use Database\Seeders\EmpresaContatoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EmpresaContatoTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_and_company_tables_exist(): void
    {
        foreach ([
            'ufs',
            'tipos_pessoa',
            'setores',
            'canais_contato',
            'status_consentimentos',
            'finalidades_consentimento',
            'status_conflitos',
            'empresas',
            'contatos',
            'consentimentos_contato',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table [{$table}].");
        }
    }

    public function test_empresa_factory_persists_domain_relationships(): void
    {
        $empresa = Empresa::factory()->create();

        $this->assertModelExists($empresa);
        $this->assertModelExists($empresa->setor);
        $this->assertModelExists($empresa->uf);
        $this->assertModelExists($empresa->statusConflito);
        $this->assertModelExists($empresa->responsavel);
    }

    public function test_contato_of_company_belongs_to_empresa(): void
    {
        $contato = Contato::factory()->create();

        $this->assertNotNull($contato->empresa_id);
        $this->assertModelExists($contato->empresa);
        $this->assertSame('pj', $contato->tipoPessoa->slug);
    }

    public function test_pessoa_fisica_contact_does_not_require_empresa(): void
    {
        $contato = Contato::factory()->pessoaFisica()->create();

        $this->assertNull($contato->empresa_id);
        $this->assertSame('pf', $contato->tipoPessoa->slug);
        $this->assertNotNull($contato->cpf);
    }

    public function test_company_cnpj_must_be_unique(): void
    {
        Empresa::factory()->create(['cnpj' => '12.345.678/0001-90']);

        $this->expectException(QueryException::class);

        Empresa::factory()->create(['cnpj' => '12.345.678/0001-90']);
    }

    public function test_prototype_seeder_creates_contacts_and_companies(): void
    {
        $this->seed(EmpresaContatoSeeder::class);

        $this->assertSame(27, Uf::query()->count());
        $this->assertSame(7, Setor::query()->count());
        $this->assertSame(6, CanalContato::query()->count());
        $this->assertSame(2, TipoPessoa::query()->count());
        $this->assertSame(2, StatusConflito::query()->count());
        $this->assertSame(6, StatusConsentimento::query()->count());
        $this->assertSame(7, Empresa::query()->count());
        $this->assertSame(16, Contato::query()->count());
        $this->assertSame(8, Contato::query()->whereNotNull('empresa_id')->count());
        $this->assertSame(8, Contato::query()->whereNull('empresa_id')->count());

        $renata = Contato::query()
            ->where('email', 'renata.bonfanti@verano.ind.br')
            ->first();

        $this->assertNotNull($renata);
        $this->assertTrue($renata->registro_mesclado);
        $this->assertSame(3, $renata->consentimentos()->count());
        $this->assertSame('Metalúrgica Verano S/A', $renata->empresa?->nome);
    }
}
