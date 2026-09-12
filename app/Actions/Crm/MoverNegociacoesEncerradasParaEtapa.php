<?php

namespace App\Actions\Crm;

use App\Enums\EtapaFunilResultado;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MoverNegociacoesEncerradasParaEtapa
{
    public function __construct(
        private EnsureEtapaAtendimentosEncerrados $ensureEtapa,
        private RegistrarHistoricoNegociacao $registrarHistorico,
        private SyncConclusaoNegociacao $syncConclusao,
    ) {}

    /**
     * Move leads encerrados sem contrato (ou desqualificados) para a etapa
     * vermelha "Atendimentos encerrados".
     *
     * Critérios (união):
     * - status_atendimento = encerrado
     * - OU status_qualificacao = desqualificado (exceto contrato-fechado / ganho)
     *
     * @return array{etapa_id: int, movidas: int, ja_na_etapa: int}
     */
    public function __invoke(Funil $funil, ?User $autor = null): array
    {
        $etapa = ($this->ensureEtapa)($funil);

        $movidas = 0;
        $jaNaEtapa = 0;

        $candidatas = Negociacao::query()
            ->with(['etapaFunil:id,nome,funil_id,resultado'])
            ->where('funil_id', $funil->id)
            ->where(function ($query): void {
                $query->whereHas('statusAtendimento', fn ($q) => $q->where('slug', 'encerrado'))
                    ->orWhere(function ($q): void {
                        $q->whereHas('statusQualificacao', fn ($s) => $s->where('slug', 'desqualificado'))
                            ->whereDoesntHave('statusAtendimento', fn ($s) => $s->where('slug', 'contrato-fechado'))
                            ->whereDoesntHave(
                                'etapaFunil',
                                fn ($e) => $e->where('resultado', EtapaFunilResultado::Ganho),
                            );
                    });
            })
            ->orderBy('id')
            ->get();

        foreach ($candidatas as $negociacao) {
            if ($negociacao->etapa_funil_id === $etapa->id) {
                $jaNaEtapa++;

                continue;
            }

            DB::transaction(function () use ($negociacao, $etapa, $autor, &$movidas): void {
                $anterior = $negociacao->etapaFunil;

                $negociacao->forceFill([
                    'etapa_funil_id' => $etapa->id,
                    'etapa_desde' => now(),
                ])->save();

                $negociacao->unsetRelations();
                $fresh = $negociacao->fresh(['funil.etapas', 'etapaFunil']);

                ($this->syncConclusao)($fresh);
                $this->registrarHistorico->etapaAlterada(
                    $fresh,
                    $anterior,
                    $etapa,
                    $autor,
                );

                $movidas++;
            });
        }

        return [
            'etapa_id' => $etapa->id,
            'movidas' => $movidas,
            'ja_na_etapa' => $jaNaEtapa,
        ];
    }
}
