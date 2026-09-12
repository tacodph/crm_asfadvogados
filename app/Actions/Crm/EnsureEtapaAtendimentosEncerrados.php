<?php

namespace App\Actions\Crm;

use App\Enums\EtapaFunilResultado;
use App\Models\EtapaFunil;
use App\Models\Funil;
use Illuminate\Support\Str;

class EnsureEtapaAtendimentosEncerrados
{
    public const NOME = 'Atendimentos encerrados';

    public const COR_FUNDO = '#9B3B2F';

    public const COR_TEXTO = '#FBF9F4';

    public const COR_SUAVE = 'rgba(251,249,244,0.92)';

    /**
     * Garante a etapa vermelha terminal "perdido" no funil e marca
     * a etapa de ganho (Fechamento) quando ainda não tipada.
     */
    public function __invoke(Funil $funil): EtapaFunil
    {
        $funil->load(['etapas' => fn ($q) => $q->orderBy('ordem')]);

        $ganho = $funil->etapas->first(
            fn (EtapaFunil $etapa): bool => $etapa->resultado === EtapaFunilResultado::Ganho,
        );

        if ($ganho === null) {
            $candidata = $funil->etapas->first(
                fn (EtapaFunil $etapa): bool => Str::lower(trim($etapa->nome)) === 'fechamento',
            ) ?? $funil->etapas
                ->filter(fn (EtapaFunil $etapa): bool => $etapa->resultado !== EtapaFunilResultado::Perdido)
                ->sortByDesc('ordem')
                ->first();

            if ($candidata !== null) {
                $candidata->forceFill(['resultado' => EtapaFunilResultado::Ganho])->save();
            }
        }

        $existente = EtapaFunil::query()
            ->where('funil_id', $funil->id)
            ->where('resultado', EtapaFunilResultado::Perdido)
            ->where('nome', self::NOME)
            ->first();

        if ($existente !== null) {
            $existente->forceFill([
                'cor_fundo' => self::COR_FUNDO,
                'cor_texto' => self::COR_TEXTO,
                'cor_suave' => self::COR_SUAVE,
                'exige_motivo' => true,
                'sla' => '—',
            ])->save();

            return $existente->fresh();
        }

        $maxOrdem = (int) EtapaFunil::query()->where('funil_id', $funil->id)->max('ordem');

        return EtapaFunil::query()->create([
            'funil_id' => $funil->id,
            'nome' => self::NOME,
            'sla' => '—',
            'campos' => ['motivo do encerramento', 'desqualificação'],
            'exige_motivo' => true,
            'resultado' => EtapaFunilResultado::Perdido,
            'ordem' => $maxOrdem + 1,
            'cor_fundo' => self::COR_FUNDO,
            'cor_texto' => self::COR_TEXTO,
            'cor_suave' => self::COR_SUAVE,
        ]);
    }
}
