<?php

namespace Tests\Unit;

use App\Enums\MetaEventName;
use App\Models\Negociacao;
use App\Support\Meta\CapiPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CapiPayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_minimal_system_generated_event(): void
    {
        $momento = Carbon::parse('2026-09-01 12:00:00');

        $payload = (new CapiPayloadBuilder)->build(
            MetaEventName::Lead,
            'lead_10',
            $momento,
            'system_generated',
            ['em' => ['abc']],
        );

        $this->assertSame('Lead', $payload['event_name']);
        $this->assertSame($momento->getTimestamp(), $payload['event_time']);
        $this->assertSame('lead_10', $payload['event_id']);
        $this->assertSame(['em' => ['abc']], $payload['user_data']);
        $this->assertArrayNotHasKey('event_source_url', $payload);
        $this->assertArrayNotHasKey('custom_data', $payload);
    }

    public function test_event_source_url_only_when_action_source_is_website(): void
    {
        $builder = new CapiPayloadBuilder;

        $semUrl = $builder->build(MetaEventName::Lead, 'x', now(), 'system_generated', [], [], 'https://site.test');
        $comUrl = $builder->build(MetaEventName::Lead, 'x', now(), 'website', [], [], 'https://site.test');

        $this->assertArrayNotHasKey('event_source_url', $semUrl);
        $this->assertSame('https://site.test', $comUrl['event_source_url']);
    }

    public function test_custom_data_drops_null_and_empty_values(): void
    {
        $payload = (new CapiPayloadBuilder)->build(
            MetaEventName::Purchase,
            'x',
            now(),
            'system_generated',
            [],
            ['currency' => 'BRL', 'value' => null, 'content_name' => ''],
        );

        $this->assertSame(['currency' => 'BRL'], $payload['custom_data']);
    }

    public function test_custom_data_para_negociacao_omits_value_when_zero(): void
    {
        $builder = new CapiPayloadBuilder;

        $semValor = Negociacao::factory()->create(['valor' => 0]);
        $comValor = Negociacao::factory()->create(['valor' => 1500]);

        $zero = $builder->customDataParaNegociacao($semValor, 'Campanha X');
        $this->assertNull($zero['value']);
        $this->assertNull($zero['currency']);

        $ok = $builder->customDataParaNegociacao($comValor, 'Campanha X');
        $this->assertSame(1500.0, $ok['value']);
        $this->assertSame('BRL', $ok['currency']);
    }
}
