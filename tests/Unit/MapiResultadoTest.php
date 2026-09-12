<?php

namespace Tests\Unit;

use App\Support\Meta\MapiResultado;
use PHPUnit\Framework\TestCase;

class MapiResultadoTest extends TestCase
{
    public function test_retentavel_cobre_throttle_rede_e_erro_de_servidor(): void
    {
        $this->assertTrue((new MapiResultado(ok: false, throttled: true))->retentavel());
        $this->assertTrue((new MapiResultado(ok: false))->retentavel()); // sem httpStatus

        foreach ([408, 429, 500, 503] as $status) {
            $this->assertTrue(
                (new MapiResultado(ok: false, httpStatus: $status))->retentavel(),
                "HTTP {$status} deveria ser retentável",
            );
        }

        foreach ([400, 401, 403, 404] as $status) {
            $this->assertFalse(
                (new MapiResultado(ok: false, httpStatus: $status, errorCode: '190'))->retentavel(),
                "HTTP {$status} não deveria ser retentável",
            );
        }

        $this->assertFalse((new MapiResultado(ok: true, httpStatus: 200))->retentavel());
    }
}
