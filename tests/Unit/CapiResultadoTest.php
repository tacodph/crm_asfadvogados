<?php

namespace Tests\Unit;

use App\Support\Meta\CapiResultado;
use PHPUnit\Framework\TestCase;

class CapiResultadoTest extends TestCase
{
    public function test_retentavel_cobre_rede_throttling_e_erro_de_servidor(): void
    {
        // Sem resposta (rede caiu) → tenta de novo.
        $this->assertTrue((new CapiResultado(ok: false))->retentavel());

        foreach ([408, 429, 500, 503] as $status) {
            $this->assertTrue(
                (new CapiResultado(ok: false, httpStatus: $status))->retentavel(),
                "HTTP {$status} deveria ser retentável",
            );
        }

        // Erros de cliente (token inválido 190/400, permissão 403, 404) → não adianta repetir.
        foreach ([400, 401, 403, 404] as $status) {
            $this->assertFalse(
                (new CapiResultado(ok: false, httpStatus: $status, errorCode: '190'))->retentavel(),
                "HTTP {$status} não deveria ser retentável",
            );
        }

        $this->assertFalse((new CapiResultado(ok: true, httpStatus: 200, eventsReceived: 1))->retentavel());
    }
}
