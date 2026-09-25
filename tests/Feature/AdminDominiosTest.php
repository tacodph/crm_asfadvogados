<?php

namespace Tests\Feature;

use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\EtapaFunil;
use App\Models\FinalidadeConsentimento;
use App\Models\Funil;
use App\Models\Setor;
use App\Models\StatusComercial;
use App\Models\StatusConflito;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminDominiosTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_hub_is_available_to_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('admin.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/admin/Index')
                ->has('modulos', 8));
    }

    public function test_can_create_and_update_finalidade_consentimento(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post($this->tenantUrl('admin.finalidades-consentimento.store'), [
                'nome' => 'Remarketing jurídico',
                'slug' => '',
                'ordem' => 4,
            ])
            ->assertRedirect($this->tenantUrl('admin.finalidades-consentimento.index'));

        $finalidade = FinalidadeConsentimento::query()
            ->where('nome', 'Remarketing jurídico')
            ->first();

        $this->assertNotNull($finalidade);
        $this->assertSame('remarketing-juridico', $finalidade->slug);

        $this->actingAs($user)
            ->patch($this->tenantUrl('admin.finalidades-consentimento.update', [
                'finalidade' => $finalidade,
            ]), [
                'nome' => 'Remarketing atualizado',
                'slug' => 'remarketing-atualizado',
                'ordem' => 5,
            ])
            ->assertRedirect($this->tenantUrl('admin.finalidades-consentimento.index'));

        $this->assertSame('Remarketing atualizado', $finalidade->fresh()->nome);
    }

    public function test_can_create_and_update_status_consentimento(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post($this->tenantUrl('admin.status-consentimentos.store'), [
                'nome' => 'parcial',
                'slug' => 'parcial',
                'cor_fundo' => '#EEF2FF',
                'cor_texto' => '#3730A3',
                'visivel_cadastro' => true,
                'ordem' => 9,
            ])
            ->assertRedirect($this->tenantUrl('admin.status-consentimentos.index'));

        $status = StatusConsentimento::query()->where('slug', 'parcial')->first();
        $this->assertNotNull($status);

        $this->actingAs($user)
            ->patch($this->tenantUrl('admin.status-consentimentos.update', [
                'status_consentimento' => $status,
            ]), [
                'nome' => 'parcial revisado',
                'slug' => 'parcial-revisado',
                'cor_fundo' => '#EEF2FF',
                'cor_texto' => '#3730A3',
                'visivel_cadastro' => false,
                'ordem' => 10,
            ])
            ->assertRedirect($this->tenantUrl('admin.status-consentimentos.index'));

        $this->assertSame('parcial revisado', $status->fresh()->nome);
        $this->assertFalse($status->fresh()->visivel_cadastro);
    }

    public function test_can_create_and_update_status_comercial(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post($this->tenantUrl('admin.status-comerciais.store'), [
                'nome' => 'Em follow-up',
                'slug' => 'em-follow-up',
                'descricao' => 'Aguardando retorno do lead.',
                'cor_fundo' => '#E8EEF6',
                'cor_texto' => '#3F5E8C',
                'ordem' => 8,
            ])
            ->assertRedirect($this->tenantUrl('admin.status-comerciais.index'));

        $status = StatusComercial::query()->where('slug', 'em-follow-up')->first();
        $this->assertNotNull($status);

        $this->actingAs($user)
            ->patch($this->tenantUrl('admin.status-comerciais.update', [
                'status_comercial' => $status,
            ]), [
                'nome' => 'Em follow-up revisado',
                'slug' => 'em-follow-up-revisado',
                'descricao' => 'Aguardando retorno do lead ou da empresa.',
                'cor_fundo' => '#E8EEF6',
                'cor_texto' => '#3F5E8C',
                'ordem' => 9,
            ])
            ->assertRedirect($this->tenantUrl('admin.status-comerciais.index'));

        $this->assertSame('Em follow-up revisado', $status->fresh()->nome);
    }

    public function test_cannot_delete_status_comercial_when_linked(): void
    {
        $user = User::factory()->create();
        $status = StatusComercial::factory()->create();
        Contato::factory()->pessoaFisica()->create([
            'status_comercial_id' => $status->id,
        ]);

        $this->actingAs($user)
            ->from($this->tenantUrl('admin.status-comerciais.index'))
            ->delete($this->tenantUrl('admin.status-comerciais.destroy', [
                'status_comercial' => $status,
            ]))
            ->assertRedirect($this->tenantUrl('admin.status-comerciais.index'))
            ->assertSessionHasErrors('status');

        $this->assertModelExists($status);
    }

    public function test_cannot_delete_status_when_linked_to_contacts(): void
    {
        $user = User::factory()->create();
        $status = StatusConsentimento::factory()->create();
        Contato::factory()->pessoaFisica()->create([
            'status_consentimento_id' => $status->id,
        ]);

        $this->actingAs($user)
            ->from($this->tenantUrl('admin.status-consentimentos.index'))
            ->delete($this->tenantUrl('admin.status-consentimentos.destroy', [
                'status_consentimento' => $status,
            ]))
            ->assertRedirect($this->tenantUrl('admin.status-consentimentos.index'))
            ->assertSessionHasErrors('status');

        $this->assertModelExists($status);
    }

    public function test_can_create_and_update_setor(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post($this->tenantUrl('admin.setores.store'), [
                'nome' => 'Energia renovável',
                'slug' => '',
                'ordem' => 8,
            ])
            ->assertRedirect($this->tenantUrl('admin.setores.index'));

        $setor = Setor::query()->where('slug', 'energia-renovavel')->first();
        $this->assertNotNull($setor);

        $this->actingAs($user)
            ->patch($this->tenantUrl('admin.setores.update', ['setor' => $setor]), [
                'nome' => 'Energia limpa',
                'slug' => 'energia-limpa',
                'ordem' => 9,
            ])
            ->assertRedirect($this->tenantUrl('admin.setores.index'));

        $this->assertSame('Energia limpa', $setor->fresh()->nome);
    }

    public function test_cannot_delete_setor_when_linked_to_empresas(): void
    {
        $user = User::factory()->create();
        $setor = Setor::factory()->create();
        Empresa::factory()->create(['setor_id' => $setor->id]);

        $this->actingAs($user)
            ->from($this->tenantUrl('admin.setores.index'))
            ->delete($this->tenantUrl('admin.setores.destroy', ['setor' => $setor]))
            ->assertRedirect($this->tenantUrl('admin.setores.index'))
            ->assertSessionHasErrors('setor');

        $this->assertModelExists($setor);
    }

    public function test_can_create_and_update_canal_contato(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post($this->tenantUrl('admin.canais-contato.store'), [
                'nome' => 'LinkedIn',
                'slug' => '',
                'cor' => '#0A66C2',
                'ordem' => 7,
            ])
            ->assertRedirect($this->tenantUrl('admin.canais-contato.index'));

        $canal = CanalContato::query()->where('slug', 'linkedin')->first();
        $this->assertNotNull($canal);

        $this->actingAs($user)
            ->patch($this->tenantUrl('admin.canais-contato.update', [
                'canal_contato' => $canal,
            ]), [
                'nome' => 'LinkedIn Ads',
                'slug' => 'linkedin-ads',
                'cor' => '#0A66C2',
                'ordem' => 8,
            ])
            ->assertRedirect($this->tenantUrl('admin.canais-contato.index'));

        $this->assertSame('LinkedIn Ads', $canal->fresh()->nome);
    }

    public function test_can_create_and_update_status_conflito(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post($this->tenantUrl('admin.status-conflitos.store'), [
                'nome' => 'em análise',
                'slug' => 'em-analise',
                'cor_fundo' => '#FBF1DF',
                'cor_texto' => '#8C6F3F',
                'cor_fundo_detalhe' => '#FBF6EC',
                'cor_borda_detalhe' => '#E8D9B8',
                'ordem' => 4,
            ])
            ->assertRedirect($this->tenantUrl('admin.status-conflitos.index'));

        $status = StatusConflito::query()->where('slug', 'em-analise')->first();
        $this->assertNotNull($status);

        $this->actingAs($user)
            ->patch($this->tenantUrl('admin.status-conflitos.update', [
                'status_conflito' => $status,
            ]), [
                'nome' => 'em análise revisado',
                'slug' => 'em-analise-revisado',
                'cor_fundo' => '#FBF1DF',
                'cor_texto' => '#8C6F3F',
                'cor_fundo_detalhe' => '#FBF6EC',
                'cor_borda_detalhe' => '#E8D9B8',
                'ordem' => 5,
            ])
            ->assertRedirect($this->tenantUrl('admin.status-conflitos.index'));

        $this->assertSame('em análise revisado', $status->fresh()->nome);
    }

    public function test_can_create_and_update_tipo_pessoa(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post($this->tenantUrl('admin.tipos-pessoa.store'), [
                'nome' => 'Espólio',
                'slug' => '',
                'ordem' => 3,
            ])
            ->assertRedirect($this->tenantUrl('admin.tipos-pessoa.index'));

        $tipo = TipoPessoa::query()->where('slug', 'espolio')->first();
        $this->assertNotNull($tipo);

        $this->actingAs($user)
            ->patch($this->tenantUrl('admin.tipos-pessoa.update', [
                'tipo_pessoa' => $tipo,
            ]), [
                'nome' => 'Espólio / inventário',
                'slug' => 'espolio-inventario',
                'ordem' => 4,
            ])
            ->assertRedirect($this->tenantUrl('admin.tipos-pessoa.index'));

        $this->assertSame('Espólio / inventário', $tipo->fresh()->nome);
    }

    public function test_can_manage_funil_and_etapas(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('admin.funis.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/admin/Funis')
                ->has('funis'));

        $this->actingAs($user)
            ->post($this->tenantUrl('admin.funis.store'), [
                'nome' => 'Funil consultivo',
                'slug' => '',
                'distribuicao' => 'round robin simples',
                'ordem' => 3,
            ])
            ->assertRedirect($this->tenantUrl('admin.funis.index'));

        $funil = Funil::query()->where('slug', 'funil-consultivo')->first();
        $this->assertNotNull($funil);

        $this->actingAs($user)
            ->post($this->tenantUrl('admin.funis.etapas.store', ['funil' => $funil]), [
                'nome' => 'Qualificação',
                'sla' => '2d',
                'campos' => 'origem, interesse',
                'exige_motivo' => false,
                'ordem' => 0,
                'cor_fundo' => '#14574F',
                'cor_texto' => '#FBF9F4',
                'cor_suave' => 'rgba(251,249,244,0.9)',
            ])
            ->assertRedirect($this->tenantUrl('admin.funis.edit', ['funil' => $funil]));

        $etapa = EtapaFunil::query()->where('funil_id', $funil->id)->first();
        $this->assertNotNull($etapa);
        $this->assertSame(['origem', 'interesse'], $etapa->campos);

        EtapaFunil::factory()->for($funil)->create([
            'nome' => 'Proposta',
            'ordem' => 2,
        ]);

        $this->actingAs($user)
            ->withoutVite()
            ->get($this->tenantUrl('admin.funis.etapas.edit', [
                'funil' => $funil,
                'etapa' => $etapa,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('crm/admin/EtapaFunilEdit')
                ->has('etapas', 2)
                ->where('etapas.0.nome', 'Qualificação')
                ->where('etapas.0.ordem', 0)
                ->where('etapas.1.nome', 'Proposta')
                ->where('etapas.1.ordem', 2));

        $this->actingAs($user)
            ->patch($this->tenantUrl('admin.funis.etapas.update', [
                'funil' => $funil,
                'etapa' => $etapa,
            ]), [
                'nome' => 'Qualificação avançada',
                'sla' => '3d',
                'campos' => 'origem, interesse, orçamento',
                'exige_motivo' => true,
                'ordem' => 2,
                'cor_fundo' => '#14574F',
                'cor_texto' => '#FBF9F4',
                'cor_suave' => 'rgba(251,249,244,0.9)',
            ])
            ->assertRedirect($this->tenantUrl('admin.funis.edit', ['funil' => $funil]));

        $this->assertSame('Qualificação avançada', $etapa->fresh()->nome);
        $this->assertTrue($etapa->fresh()->exige_motivo);
    }

    public function test_store_etapa_normalizes_invalid_css_color_values(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();

        $this->actingAs($user)
            ->post($this->tenantUrl('admin.funis.etapas.store', ['funil' => $funil]), [
                'nome' => 'Nova etapa',
                'sla' => '1d',
                'campos' => '',
                'exige_motivo' => false,
                'ordem' => 0,
                'cor_fundo' => 'var(--accent)',
                'cor_texto' => 'var(--primary-foreground)',
                'cor_suave' => 'rgba(251,249,244,0.9)',
            ])
            ->assertRedirect($this->tenantUrl('admin.funis.edit', ['funil' => $funil]));

        $etapa = EtapaFunil::query()->where('funil_id', $funil->id)->first();
        $this->assertNotNull($etapa);
        $this->assertSame('Nova etapa', $etapa->nome);
        $this->assertSame('#14574F', $etapa->cor_fundo);
        $this->assertSame('#FBF9F4', $etapa->cor_texto);
    }

    public function test_funil_edit_page_uses_hex_defaults_for_new_etapa_colors(): void
    {
        $source = file_get_contents(resource_path('js/pages/crm/admin/FunilEdit.vue'));

        $this->assertNotFalse($source);
        $this->assertStringContainsString("cor_fundo: '#14574F'", $source);
        $this->assertStringContainsString("cor_texto: '#FBF9F4'", $source);
        $this->assertStringNotContainsString("cor_fundo: 'var(--accent)'", $source);
    }

    public function test_store_etapa_rejects_ordem_greater_than_etapas_count(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();

        EtapaFunil::factory()->for($funil)->create(['ordem' => 1]);
        EtapaFunil::factory()->for($funil)->create(['ordem' => 2]);

        $this->actingAs($user)
            ->from($this->tenantUrl('admin.funis.edit', ['funil' => $funil]))
            ->post($this->tenantUrl('admin.funis.etapas.store', ['funil' => $funil]), [
                'nome' => 'Etapa inválida',
                'sla' => '1d',
                'campos' => '',
                'exige_motivo' => false,
                'ordem' => 3,
                'cor_fundo' => '#14574F',
                'cor_texto' => '#FBF9F4',
                'cor_suave' => 'rgba(251,249,244,0.9)',
            ])
            ->assertRedirect($this->tenantUrl('admin.funis.edit', ['funil' => $funil]))
            ->assertSessionHasErrors(['ordem']);

        $this->assertSame(2, EtapaFunil::query()->where('funil_id', $funil->id)->count());
    }

    public function test_store_etapa_shifts_existing_ordens_when_ordem_conflicts(): void
    {
        $user = User::factory()->create();
        $funil = Funil::factory()->create();

        $primeira = EtapaFunil::factory()->for($funil)->create([
            'nome' => 'Triagem',
            'ordem' => 1,
        ]);
        $segunda = EtapaFunil::factory()->for($funil)->create([
            'nome' => 'Proposta',
            'ordem' => 2,
        ]);
        $terceira = EtapaFunil::factory()->for($funil)->create([
            'nome' => 'Fechamento',
            'ordem' => 3,
        ]);

        $this->actingAs($user)
            ->post($this->tenantUrl('admin.funis.etapas.store', ['funil' => $funil]), [
                'nome' => 'Qualificação',
                'sla' => '1d',
                'campos' => '',
                'exige_motivo' => false,
                'ordem' => 2,
                'cor_fundo' => '#14574F',
                'cor_texto' => '#FBF9F4',
                'cor_suave' => 'rgba(251,249,244,0.9)',
            ])
            ->assertRedirect($this->tenantUrl('admin.funis.edit', ['funil' => $funil]));

        $nova = EtapaFunil::query()
            ->where('funil_id', $funil->id)
            ->where('nome', 'Qualificação')
            ->first();

        $this->assertNotNull($nova);
        $this->assertSame(2, $nova->ordem);
        $this->assertSame(1, $primeira->fresh()->ordem);
        $this->assertSame(3, $segunda->fresh()->ordem);
        $this->assertSame(4, $terceira->fresh()->ordem);
    }
}
