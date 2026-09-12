<?php

namespace Tests\Unit;

use App\Models\Contato;
use App\Models\Empresa;
use App\Models\Uf;
use App\Support\Meta\AdvancedMatching;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_is_trimmed_lowercased_and_hashed(): void
    {
        $this->assertSame(
            hash('sha256', 'foo@bar.com'),
            AdvancedMatching::email(' Foo@Bar.com '),
        );
    }

    public function test_phone_keeps_only_digits_and_adds_country_code(): void
    {
        $this->assertSame(
            hash('sha256', '5511987654321'),
            AdvancedMatching::phone('(11) 98765-4321'),
        );
    }

    public function test_phone_with_country_code_is_left_alone(): void
    {
        $this->assertSame(
            hash('sha256', '5511987654321'),
            AdvancedMatching::phone('+55 11 98765-4321'),
        );
    }

    public function test_empty_and_null_values_hash_to_null(): void
    {
        $this->assertNull(AdvancedMatching::hash(null));
        $this->assertNull(AdvancedMatching::hash('   '));
        $this->assertNull(AdvancedMatching::email(null));
        $this->assertNull(AdvancedMatching::phone(null));
        $this->assertNull(AdvancedMatching::zip(''));
        $this->assertNull(AdvancedMatching::state(null));
        $this->assertNull(AdvancedMatching::city(null));
    }

    public function test_zip_takes_first_five_digits(): void
    {
        $this->assertSame(hash('sha256', '01310'), AdvancedMatching::zip('01310-100'));
    }

    public function test_hashes_are_64_hex_chars(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) AdvancedMatching::country());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', AdvancedMatching::externalId(42));
    }

    public function test_user_data_from_contato_has_only_hashed_arrays(): void
    {
        $contato = Contato::factory()->create([
            'nome' => 'João Silva Souza',
            'email' => 'JOAO@Exemplo.COM',
            'telefone' => '11 3333-4444',
            'cidade' => 'São Paulo',
        ]);

        $userData = AdvancedMatching::userDataFrom($contato, [
            'fbp' => 'fb.1.123.456',
            'client_ip_address' => '203.0.113.9',
            'fbc' => '',
        ]);

        $this->assertSame([AdvancedMatching::email($contato->email)], $userData['em']);
        $this->assertSame([AdvancedMatching::name('João')], $userData['fn']);
        $this->assertSame([AdvancedMatching::name('Silva Souza')], $userData['ln']);
        $this->assertSame([AdvancedMatching::city($contato->cidade)], $userData['ct']);
        $this->assertSame([AdvancedMatching::externalId($contato->id)], $userData['external_id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $userData['em'][0]);

        // Texto puro, não hasheado.
        $this->assertSame('fb.1.123.456', $userData['fbp']);
        $this->assertSame('203.0.113.9', $userData['client_ip_address']);

        // Vazio/ausente não entra.
        $this->assertArrayNotHasKey('fbc', $userData);
        $this->assertArrayNotHasKey('client_user_agent', $userData);
    }

    public function test_zip_is_included_and_city_falls_back_to_empresa(): void
    {
        $empresa = Empresa::factory()->create([
            'cidade' => 'Curitiba',
            'uf_id' => Uf::query()->firstOrCreate(['sigla' => 'PR'], ['nome' => 'Paraná', 'ordem' => 16])->id,
        ]);
        $contato = Contato::factory()->create([
            'empresa_id' => $empresa->id,
            'cidade' => null,
            'uf_id' => null,
            'cep' => '80010-010',
        ]);

        $userData = AdvancedMatching::userDataFrom($contato, [], $empresa);

        $this->assertSame([AdvancedMatching::zip('80010-010')], $userData['zp']);
        $this->assertSame([AdvancedMatching::city('Curitiba')], $userData['ct']);
        $this->assertSame([AdvancedMatching::state('PR')], $userData['st']);
    }
}
