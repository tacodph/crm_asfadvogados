<?php

namespace Tests\Feature;

use App\Models\MetaConversaoConfig;
use App\Models\MetaConversaoEstatistica;
use App\Models\MetaConversaoEvento;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MetaCapiIsolamentoTenantTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $outro;

    private MetaConversaoConfig $configAlheia;

    private MetaConversaoEvento $eventoAlheio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();

        $this->outro = $this->createTenant();

        [$this->configAlheia, $this->eventoAlheio] = $this->asTenant($this->outro, function (): array {
            $config = MetaConversaoConfig::factory()->create(['slug' => 'alheia']);
            $evento = MetaConversaoEvento::factory()->comErro()->create(['meta_conversao_config_id' => $config->id]);
            MetaConversaoEstatistica::factory()->create(['meta_conversao_config_id' => $config->id]);

            return [$config, $evento];
        });
    }

    public function test_tenant_b_nao_ve_dados_do_tenant_a_em_nenhuma_rota_trafego(): void
    {
        $this->actingAs(User::factory()->create()); // usuário do tenant padrão (B)

        $this->get(route('trafego.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('campanhas', [])->where('saude.campanhas', []));

        $this->get(route('trafego.eventos.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('eventos.total', 0));

        $this->get(route('trafego.diagnostico.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('campanhas', []));

        $this->patch(route('trafego.update', $this->configAlheia))->assertNotFound();
        $this->delete(route('trafego.destroy', $this->configAlheia))->assertNotFound();
        $this->get(route('trafego.eventos.show', $this->eventoAlheio))->assertNotFound();
        $this->post(route('trafego.eventos.reenviar', $this->eventoAlheio))->assertNotFound();
    }
}
