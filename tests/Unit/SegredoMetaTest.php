<?php

namespace Tests\Unit;

use App\Casts\SegredoMeta;
use App\Models\MetaConversaoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class SegredoMetaTest extends TestCase
{
    use RefreshDatabase;

    public function test_round_trip_encrypts_and_decrypts(): void
    {
        $cast = new SegredoMeta;
        $model = new MetaConversaoConfig;

        $cifrado = $cast->set($model, 'access_token', 'EAAtoken-super-secreto', []);

        $this->assertIsString($cifrado);
        $this->assertNotSame('EAAtoken-super-secreto', $cifrado);
        $this->assertStringNotContainsString('EAAtoken-super-secreto', $cifrado);
        $this->assertSame(
            'EAAtoken-super-secreto',
            $cast->get($model, 'access_token', $cifrado, []),
        );
    }

    public function test_null_stays_null(): void
    {
        $cast = new SegredoMeta;
        $model = new MetaConversaoConfig;

        $this->assertNull($cast->set($model, 'test_event_code', null, []));
        $this->assertNull($cast->get($model, 'test_event_code', null, []));
    }

    public function test_missing_key_throws_clear_exception(): void
    {
        config(['meta.capi.encryption_key' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('META_CAPI_ENCRYPTION_KEY');

        (new SegredoMeta)->set(new MetaConversaoConfig, 'access_token', 'x', []);
    }

    public function test_invalid_key_length_throws(): void
    {
        config(['meta.capi.encryption_key' => 'base64:'.base64_encode('curta-demais')]);

        $this->expectException(RuntimeException::class);

        (new SegredoMeta)->set(new MetaConversaoConfig, 'access_token', 'x', []);
    }
}
