<?php

namespace App\Actions\Crm;

use App\Models\IbgeMunicipio;
use App\Models\Uf;
use Illuminate\Validation\ValidationException;

class ResolveLocalidadeFromMunicipio
{
    /**
     * @return array{cidade: string, uf_id: int, municipio_id: int}
     */
    public function __invoke(int $municipioId): array
    {
        $municipio = IbgeMunicipio::query()
            ->with('estado:id,txt_sigla_uf,txt_uf')
            ->find($municipioId);

        if ($municipio === null || blank($municipio->txt_nome_municipios)) {
            throw ValidationException::withMessages([
                'municipio_id' => 'Município IBGE inválido.',
            ]);
        }

        $sigla = strtoupper((string) $municipio->estado?->txt_sigla_uf);

        if ($sigla === '') {
            throw ValidationException::withMessages([
                'municipio_id' => 'Município sem UF IBGE associada.',
            ]);
        }

        $uf = Uf::query()->whereRaw('UPPER(sigla) = ?', [$sigla])->first();

        if ($uf === null) {
            throw ValidationException::withMessages([
                'municipio_id' => "UF {$sigla} não cadastrada no CRM.",
            ]);
        }

        return [
            'cidade' => (string) $municipio->txt_nome_municipios,
            'uf_id' => $uf->id,
            'municipio_id' => $municipio->id,
        ];
    }
}
