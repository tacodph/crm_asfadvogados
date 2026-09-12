<?php

namespace App\Actions\Crm;

use App\Models\CanalContato;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\EtapaFunil;
use App\Models\Funil;
use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
use App\Models\StatusAtendimento;
use App\Models\StatusQualificacao;
use App\Models\TarefaNegociacao;
use App\Models\User;
use Illuminate\Support\Carbon;

class RegistrarHistoricoNegociacao
{
    /**
     * @param  array<string, mixed>|null  $antes
     */
    public function __invoke(
        Negociacao $negociacao,
        string $tipo,
        string $titulo,
        string $descricao,
        ?string $autor = null,
        ?Carbon $ocorridoEm = null,
    ): HistoricoNegociacao {
        return HistoricoNegociacao::query()->create([
            'negociacao_id' => $negociacao->id,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'autor' => $autor ?? $this->autorPadrao(),
            'ocorrido_em' => $ocorridoEm ?? now(),
        ]);
    }

    public function criacao(Negociacao $negociacao, ?User $usuario = null): HistoricoNegociacao
    {
        $negociacao->loadMissing([
            'funil:id,nome',
            'etapaFunil:id,nome',
            'contato:id,nome',
            'responsavel:id,name',
        ]);

        $descricao = sprintf(
            'Negociação criada no funil “%s”, etapa “%s”, contato “%s”, responsável “%s”. Valor estimado: %s.',
            $negociacao->funil->nome,
            $negociacao->etapaFunil->nome,
            $negociacao->contato->nome,
            $negociacao->responsavel->name,
            $this->formatarValor((float) $negociacao->valor),
        );

        return ($this)(
            $negociacao,
            'sys',
            'Negociação criada',
            $descricao,
            $usuario?->name,
        );
    }

    public function etapaAlterada(
        Negociacao $negociacao,
        EtapaFunil $etapaAnterior,
        EtapaFunil $etapaNova,
        ?User $usuario = null,
    ): HistoricoNegociacao {
        return ($this)(
            $negociacao,
            'sys',
            'Etapa alterada',
            sprintf(
                'Etapa movida de “%s” para “%s”.',
                $etapaAnterior->nome,
                $etapaNova->nome,
            ),
            $usuario?->name,
        );
    }

    /**
     * @param  array{
     *     funil_id: int,
     *     etapa_funil_id: int,
     *     contato_id: int,
     *     empresa_id: int|null,
     *     canal_contato_id: int,
     *     status_atendimento_id?: int|null,
     *     status_qualificacao_id?: int|null,
     *     motivo_desqualificacao?: string|null,
     *     continuidade_atendimento?: string|null,
     *     observacoes_complementares?: string|null,
     *     responsavel_user_id: int|null,
     *     assunto: string,
     *     valor: mixed,
     *     previsao_fechamento: mixed
     * }  $antes
     */
    public function atualizacao(
        Negociacao $negociacao,
        array $antes,
        ?User $usuario = null,
    ): ?HistoricoNegociacao {
        $negociacao->loadMissing([
            'funil:id,nome',
            'etapaFunil:id,nome',
            'contato:id,nome',
            'empresa:id,nome',
            'canalContato:id,nome',
            'statusAtendimento:id,nome',
            'statusQualificacao:id,nome',
            'responsavel:id,name',
        ]);

        $mudancas = $this->diffAlteracoes($negociacao, $antes);

        if ($mudancas === []) {
            return null;
        }

        return ($this)(
            $negociacao,
            'sys',
            'Negociação atualizada',
            implode("\n", $mudancas),
            $usuario?->name,
        );
    }

    public function tarefaCriada(
        Negociacao $negociacao,
        TarefaNegociacao $tarefa,
        ?User $usuario = null,
    ): HistoricoNegociacao {
        return ($this)(
            $negociacao,
            'task',
            'Tarefa cadastrada',
            $this->descricaoTarefa($tarefa),
            $usuario?->name,
        );
    }

    public function tarefaAtualizada(
        Negociacao $negociacao,
        TarefaNegociacao $tarefa,
        ?User $usuario = null,
    ): HistoricoNegociacao {
        $tarefa->refresh();

        return ($this)(
            $negociacao,
            'task',
            'Tarefa atualizada',
            $this->descricaoTarefa($tarefa),
            $usuario?->name,
        );
    }

    public function tarefaRemovida(
        Negociacao $negociacao,
        string $descricaoTarefa,
        ?User $usuario = null,
    ): HistoricoNegociacao {
        return ($this)(
            $negociacao,
            'task',
            'Tarefa removida',
            'Tarefa “'.$descricaoTarefa.'” removida da negociação.',
            $usuario?->name,
        );
    }

    /**
     * @param  array{
     *     funil_id: int,
     *     etapa_funil_id: int,
     *     contato_id: int,
     *     empresa_id: int|null,
     *     canal_contato_id: int,
     *     responsavel_user_id: int|null,
     *     assunto: string,
     *     valor: mixed,
     *     previsao_fechamento: mixed
     * }  $antes
     * @return list<string>
     */
    private function diffAlteracoes(Negociacao $negociacao, array $antes): array
    {
        $mudancas = [];

        if ((int) $antes['funil_id'] !== (int) $negociacao->funil_id) {
            $mudancas[] = sprintf(
                'Funil: “%s” → “%s”.',
                Funil::query()->find($antes['funil_id'])?->nome ?? '#'.$antes['funil_id'],
                $negociacao->funil?->nome ?? '#'.$negociacao->funil_id,
            );
        }

        if ((int) $antes['etapa_funil_id'] !== (int) $negociacao->etapa_funil_id) {
            $mudancas[] = sprintf(
                'Etapa: “%s” → “%s”.',
                EtapaFunil::query()->find($antes['etapa_funil_id'])?->nome ?? '#'.$antes['etapa_funil_id'],
                $negociacao->etapaFunil?->nome ?? '#'.$negociacao->etapa_funil_id,
            );
        }

        if ((int) $antes['contato_id'] !== (int) $negociacao->contato_id) {
            $mudancas[] = sprintf(
                'Contato: “%s” → “%s”.',
                Contato::query()->find($antes['contato_id'])?->nome ?? '#'.$antes['contato_id'],
                $negociacao->contato?->nome ?? '#'.$negociacao->contato_id,
            );
        }

        if ((int) ($antes['empresa_id'] ?? 0) !== (int) ($negociacao->empresa_id ?? 0)) {
            $mudancas[] = sprintf(
                'Empresa: “%s” → “%s”.',
                $antes['empresa_id']
                    ? (Empresa::query()->find($antes['empresa_id'])?->nome ?? '#'.$antes['empresa_id'])
                    : 'nenhuma',
                $negociacao->empresa?->nome ?? 'nenhuma',
            );
        }

        if ((int) $antes['canal_contato_id'] !== (int) $negociacao->canal_contato_id) {
            $mudancas[] = sprintf(
                'Canal: “%s” → “%s”.',
                CanalContato::query()->find($antes['canal_contato_id'])?->nome ?? '#'.$antes['canal_contato_id'],
                $negociacao->canalContato?->nome ?? '#'.$negociacao->canal_contato_id,
            );
        }

        if ((int) ($antes['status_atendimento_id'] ?? 0) !== (int) ($negociacao->status_atendimento_id ?? 0)) {
            $mudancas[] = sprintf(
                'Status do atendimento: “%s” → “%s”.',
                $antes['status_atendimento_id']
                    ? (StatusAtendimento::query()->find($antes['status_atendimento_id'])?->nome ?? '#'.$antes['status_atendimento_id'])
                    : 'não definido',
                $negociacao->statusAtendimento?->nome ?? 'não definido',
            );
        }

        if ((int) ($antes['status_qualificacao_id'] ?? 0) !== (int) ($negociacao->status_qualificacao_id ?? 0)) {
            $mudancas[] = sprintf(
                'Status da qualificação: “%s” → “%s”.',
                $antes['status_qualificacao_id']
                    ? (StatusQualificacao::query()->find($antes['status_qualificacao_id'])?->nome ?? '#'.$antes['status_qualificacao_id'])
                    : 'não definido',
                $negociacao->statusQualificacao?->nome ?? 'não definido',
            );
        }

        if ((string) ($antes['motivo_desqualificacao'] ?? '') !== (string) ($negociacao->motivo_desqualificacao ?? '')) {
            $mudancas[] = 'Motivo de desqualificação atualizado.';
        }

        if ((string) ($antes['continuidade_atendimento'] ?? '') !== (string) ($negociacao->continuidade_atendimento ?? '')) {
            $mudancas[] = 'Continuidade do atendimento atualizada.';
        }

        if ((string) ($antes['observacoes_complementares'] ?? '') !== (string) ($negociacao->observacoes_complementares ?? '')) {
            $mudancas[] = 'Observações complementares atualizadas.';
        }

        if ((int) ($antes['responsavel_user_id'] ?? 0) !== (int) ($negociacao->responsavel_user_id ?? 0)) {
            $mudancas[] = sprintf(
                'Responsável: “%s” → “%s”.',
                $antes['responsavel_user_id']
                    ? (User::query()->find($antes['responsavel_user_id'])?->name ?? '#'.$antes['responsavel_user_id'])
                    : 'não definido',
                $negociacao->responsavel?->name ?? 'não definido',
            );
        }

        if ((string) $antes['assunto'] !== (string) $negociacao->assunto) {
            $mudancas[] = sprintf(
                'Assunto: “%s” → “%s”.',
                $antes['assunto'],
                $negociacao->assunto,
            );
        }

        if (round((float) $antes['valor'], 2) !== round((float) $negociacao->valor, 2)) {
            $mudancas[] = sprintf(
                'Valor: %s → %s.',
                $this->formatarValor((float) $antes['valor']),
                $this->formatarValor((float) $negociacao->valor),
            );
        }

        $previsaoAntes = $this->formatarData($antes['previsao_fechamento'] ?? null);
        $previsaoDepois = $this->formatarData($negociacao->previsao_fechamento);

        if ($previsaoAntes !== $previsaoDepois) {
            $mudancas[] = sprintf(
                'Previsão de fechamento: %s → %s.',
                $previsaoAntes,
                $previsaoDepois,
            );
        }

        return $mudancas;
    }

    private function descricaoTarefa(TarefaNegociacao $tarefa): string
    {
        $quando = $tarefa->data->format('d/m/Y');

        if ($tarefa->hora !== null) {
            $quando .= ' '.$tarefa->hora->format('H:i');
        }

        return sprintf(
            '“%s” — %s · status %s.',
            $tarefa->descricao,
            $quando,
            $tarefa->status->label(),
        );
    }

    private function formatarValor(float $valor): string
    {
        return 'R$ '.number_format($valor, 2, ',', '.');
    }

    private function formatarData(mixed $valor): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return Carbon::parse($valor)->format('d/m/Y');
    }

    private function autorPadrao(): string
    {
        return auth()->user()?->name ?? 'Sistema';
    }
}
