<?php

namespace Tests\Feature;

use App\Models\MetaConversaoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaCapiRecriptografarTokensTest extends TestCase
{
    use RefreshDatabase;

    private const CHAVE_ANTIGA = 'base64:YWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWFhYWE=';

    private const CHAVE_NOVA = 'base64:YmJiYmJiYmJiYmJiYmJiYmJiYmJiYmJiYmJiYmJiYmI=';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function cifrado(int $id): ?string
    {
        return DB::table('meta_conversao_configs')->where('id', $id)->value('access_token');
    }

    public function test_rotaciona_a_chave_sem_invalidar_a_verificacao(): void
    {
        config(['meta.capi.encryption_key' => self::CHAVE_ANTIGA]);

        $config = MetaConversaoConfig::factory()->tokenVerificado()->create([
            'access_token' => 'EAAtoken-secreto-original-123456',
            'test_event_code' => 'TEST-abc-123',
        ]);
        $verificadoEm = $config->fresh()->token_verificado_em;
        $cifradoAntes = $this->cifrado($config->id);

        // Passa a apontar para a chave nova: o cast não decifra mais o que está salvo.
        config(['meta.capi.encryption_key' => self::CHAVE_NOVA]);

        $this->artisan('meta:recriptografar-tokens --chave-antiga='.self::CHAVE_ANTIGA.' --force')
            ->assertSuccessful();

        $config->refresh();
        $this->assertSame('EAAtoken-secreto-original-123456', $config->access_token);
        $this->assertSame('TEST-abc-123', $config->test_event_code);
        $this->assertNotSame($cifradoAntes, $this->cifrado($config->id));
        $this->assertEquals($verificadoEm, $config->token_verificado_em);
    }

    public function test_dry_run_nao_altera_nada(): void
    {
        config(['meta.capi.encryption_key' => self::CHAVE_ANTIGA]);
        $config = MetaConversaoConfig::factory()->create(['access_token' => 'EAAtoken-dry-run-000000']);
        $cifradoAntes = $this->cifrado($config->id);

        config(['meta.capi.encryption_key' => self::CHAVE_NOVA]);

        $this->artisan('meta:recriptografar-tokens --chave-antiga='.self::CHAVE_ANTIGA)
            ->assertSuccessful();

        $this->assertSame($cifradoAntes, $this->cifrado($config->id));
    }

    public function test_falha_sem_chave_antiga(): void
    {
        $this->artisan('meta:recriptografar-tokens')->assertFailed();
    }

    public function test_nao_imprime_tokens(): void
    {
        config(['meta.capi.encryption_key' => self::CHAVE_ANTIGA]);
        $config = MetaConversaoConfig::factory()->create(['access_token' => 'EAAsegredo-nao-vaza-999']);
        config(['meta.capi.encryption_key' => self::CHAVE_NOVA]);

        $this->artisan('meta:recriptografar-tokens --chave-antiga='.self::CHAVE_ANTIGA.' --force')
            ->doesntExpectOutputToContain('EAAsegredo-nao-vaza-999')
            ->assertSuccessful();
    }
}
