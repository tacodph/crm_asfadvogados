<?php

namespace Tests\Feature;

use App\Models\MetaConversaoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetaConversaoConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_access_token_is_encrypted_at_rest_and_decrypts_via_accessor(): void
    {
        $config = MetaConversaoConfig::factory()->create();
        $tokenPuro = $config->access_token;

        $cru = DB::table('meta_conversao_configs')->where('id', $config->id)->value('access_token');

        $this->assertNotSame($tokenPuro, $cru);
        $this->assertStringNotContainsString('EAA', (string) $cru);
        $this->assertSame($tokenPuro, $config->fresh()->access_token);
    }

    public function test_saving_derives_token_ultimos4_and_mask(): void
    {
        $config = MetaConversaoConfig::factory()->create(['access_token' => 'EAAabcdefgh9WXYZ']);

        $this->assertSame('WXYZ', $config->token_ultimos4);
        $this->assertSame('••••WXYZ', $config->mascararToken());
        $this->assertNull($config->token_valido);
    }

    public function test_changing_token_resets_verification(): void
    {
        $config = MetaConversaoConfig::factory()->tokenVerificado()->create();
        $this->assertTrue($config->fresh()->token_valido);

        $config->update(['access_token' => 'EAAnovotoken000abcd']);

        $config->refresh();
        $this->assertSame('abcd', $config->token_ultimos4);
        $this->assertNull($config->token_valido);
        $this->assertNull($config->token_verificado_em);
    }

    public function test_token_is_hidden_from_serialization(): void
    {
        $config = MetaConversaoConfig::factory()->create();

        $array = $config->toArray();

        $this->assertArrayNotHasKey('access_token', $array);
        $this->assertArrayNotHasKey('test_event_code', $array);
        $this->assertStringNotContainsString('EAA', json_encode($config));
    }

    public function test_decryption_survives_app_key_rotation(): void
    {
        $config = MetaConversaoConfig::factory()->create();
        $tokenPuro = $config->access_token;

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $this->assertSame($tokenPuro, $config->fresh()->access_token);
    }

    public function test_scope_ativas_filters_inactive(): void
    {
        MetaConversaoConfig::factory()->create();
        MetaConversaoConfig::factory()->inativa()->create();

        $this->assertSame(2, MetaConversaoConfig::query()->count());
        $this->assertSame(1, MetaConversaoConfig::query()->ativas()->count());
    }

    public function test_bancario_state(): void
    {
        $config = MetaConversaoConfig::factory()->bancario()->create();

        $this->assertSame('bancario', $config->slug);
        $this->assertSame('2050053805929053', $config->pixel_id);
        $this->assertSame($this->tenant->id, $config->tenant_id);
    }
}
