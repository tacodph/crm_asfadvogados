<?php

namespace Tests\Feature;

use App\Models\MetaAdsConta;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MetaAdsContaTest extends TestCase
{
    use RefreshDatabase;

    public function test_config_ads_resolve(): void
    {
        $this->assertNotSame('', (string) config('meta.ads.api_version'));
        $this->assertSame('meta-ads', config('meta.ads.queue'));
        $this->assertIsArray(config('meta.ads.insights_fields'));
        $this->assertContains('lead', config('meta.ads.acoes_resultado'));
    }

    public function test_access_token_is_encrypted_at_rest_and_decrypts_via_accessor(): void
    {
        $conta = MetaAdsConta::factory()->create();
        $tokenPuro = $conta->access_token;

        $cru = DB::table('meta_ads_contas')->where('id', $conta->id)->value('access_token');

        $this->assertNotSame($tokenPuro, $cru);
        $this->assertStringNotContainsString('EAA', (string) $cru);
        $this->assertSame($tokenPuro, $conta->fresh()->access_token);
    }

    public function test_saving_derives_token_ultimos4_and_mask(): void
    {
        $conta = MetaAdsConta::factory()->create(['access_token' => 'EAAabcdefgh9WXYZ']);

        $this->assertSame('WXYZ', $conta->token_ultimos4);
        $this->assertSame('••••WXYZ', $conta->mascararToken());
        $this->assertNull($conta->token_valido);
        $this->assertSame('act_'.$conta->ad_account_id, $conta->nodeId());
    }

    public function test_token_is_hidden_from_serialization(): void
    {
        $conta = MetaAdsConta::factory()->create();

        $this->assertArrayNotHasKey('access_token', $conta->toArray());
        $this->assertStringNotContainsString('EAA', json_encode($conta));
    }

    public function test_changing_token_resets_verification(): void
    {
        $conta = MetaAdsConta::factory()->tokenVerificado()->create();
        $this->assertTrue($conta->fresh()->token_valido);

        $conta->update(['access_token' => 'EAAnovotoken000abcd']);

        $conta->refresh();
        $this->assertSame('abcd', $conta->token_ultimos4);
        $this->assertNull($conta->token_valido);
        $this->assertNull($conta->token_verificado_em);
    }

    public function test_ad_account_id_is_unique_per_tenant(): void
    {
        MetaAdsConta::factory()->create(['ad_account_id' => '123456789012345']);

        $this->expectException(QueryException::class);
        MetaAdsConta::factory()->create(['ad_account_id' => '123456789012345']);
    }

    public function test_scope_ativas_and_tem_escopo(): void
    {
        MetaAdsConta::factory()->create();
        MetaAdsConta::factory()->inativa()->create();
        $comGestao = MetaAdsConta::factory()->comGestao()->create();

        $this->assertSame(3, MetaAdsConta::query()->count());
        $this->assertSame(2, MetaAdsConta::query()->ativas()->count());
        $this->assertTrue($comGestao->temEscopo('ads_management'));
        $this->assertFalse(MetaAdsConta::factory()->create()->temEscopo('ads_management'));
    }

    public function test_command_creates_and_updates_without_duplicating(): void
    {
        $this->artisan('meta:ads-conta', [
            '--tenant' => $this->tenant->slug,
            '--conta' => 'act_555000111222333',
            '--nome' => 'ASF — Bancário',
        ])
            ->expectsQuestion('Access token (ads_read)', 'EAAtoken-conta-0001')
            ->assertSuccessful();

        $conta = MetaAdsConta::query()->where('ad_account_id', '555000111222333')->firstOrFail();
        $this->assertSame('ASF — Bancário', $conta->nome);
        $this->assertSame('EAAtoken-conta-0001', $conta->access_token);
        $this->assertSame('0001', $conta->token_ultimos4);
        $this->assertSame($this->tenant->id, $conta->tenant_id);

        // Segunda execução: novo nome, token em branco mantém o atual.
        $this->artisan('meta:ads-conta', [
            '--tenant' => $this->tenant->slug,
            '--conta' => '555000111222333',
            '--nome' => 'ASF — Bancário (2026)',
        ])
            ->expectsQuestion('Access token (ads_read) — Enter em branco mantém o atual', '')
            ->assertSuccessful();

        $this->assertSame(1, MetaAdsConta::query()->where('ad_account_id', '555000111222333')->count());
        $conta->refresh();
        $this->assertSame('ASF — Bancário (2026)', $conta->nome);
        $this->assertSame('EAAtoken-conta-0001', $conta->access_token);
    }

    public function test_command_fails_for_unknown_tenant(): void
    {
        $this->artisan('meta:ads-conta', ['--tenant' => 'nao-existe', '--conta' => '123456'])
            ->assertFailed();
    }

    public function test_contas_are_isolated_per_tenant(): void
    {
        $outro = $this->createTenant();
        $alheia = $this->asTenant($outro, fn () => MetaAdsConta::factory()->create());

        $this->assertSame(0, MetaAdsConta::query()->whereKey($alheia->id)->count());
        $this->assertSame(
            1,
            $this->asTenant($outro, fn () => MetaAdsConta::query()->count()),
        );
    }
}
