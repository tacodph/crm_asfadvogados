<?php

namespace App\Actions\Crm;

use App\Models\EtapaFunil;
use App\Models\Funil;
use Illuminate\Support\Facades\DB;

class CriarEtapaFunil
{
    /**
     * @param  array{
     *     nome: string,
     *     sla: string,
     *     campos: list<string>,
     *     meta_evento?: string|null,
     *     exige_motivo: bool,
     *     ordem: int,
     *     cor_fundo: string,
     *     cor_texto: string,
     *     cor_suave: string
     * }  $dados
     */
    public function __invoke(Funil $funil, array $dados): EtapaFunil
    {
        return DB::transaction(function () use ($funil, $dados): EtapaFunil {
            $ordem = (int) $dados['ordem'];

            if ($funil->etapas()->where('ordem', $ordem)->exists()) {
                $funil->etapas()
                    ->where('ordem', '>=', $ordem)
                    ->increment('ordem');
            }

            return $funil->etapas()->create($dados);
        });
    }
}
