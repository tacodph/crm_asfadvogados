<?php

namespace App\Actions\Crm;

use App\Enums\EtapaFunilResultado;
use App\Enums\StatusTarefaNegociacao;
use App\Enums\TipoTarefaNegociacao;
use App\Models\Negociacao;
use App\Models\TarefaNegociacao;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Monta a agenda de um dia: follow-ups (pendentes e do dia), atividades
 * atrasadas, reuniões, demais tarefas e negociações abertas sem próximo passo.
 *
 * Com `$responsavel` nulo a agenda é da equipe inteira do tenant atual.
 */
class BuildAgendaDoDia
{
    /**
     * @return array{
     *     data: string,
     *     followUpsPendentes: list<array<string, mixed>>,
     *     followUpsHoje: list<array<string, mixed>>,
     *     atrasadas: list<array<string, mixed>>,
     *     reunioesHoje: list<array<string, mixed>>,
     *     demaisHoje: list<array<string, mixed>>,
     *     semProximoPasso: list<array<string, mixed>>,
     *     totais: array{followUpsPendentes: int, followUpsHoje: int, atrasadas: int, reunioesHoje: int, demaisHoje: int, semProximoPasso: int}
     * }
     */
    public function __invoke(?User $responsavel, CarbonInterface $agora): array
    {
        $hoje = $agora->toDateString();

        $tarefas = TarefaNegociacao::query()
            ->with([
                'responsavel:id,name',
                'negociacao:id,funil_id,etapa_funil_id,empresa_id,contato_id,assunto,ultimo_contato_em',
                'negociacao.funil:id,nome',
                'negociacao.etapaFunil:id,nome,resultado',
                'negociacao.empresa:id,nome',
                'negociacao.contato:id,nome,telefone',
            ])
            ->whereIn('status', StatusTarefaNegociacao::abertos())
            ->whereDate('data', '<=', $hoje)
            ->whereHas('negociacao', fn (Builder $query) => $this->somenteAbertas($query))
            ->when($responsavel !== null, fn (Builder $query) => $query->where('responsavel_user_id', $responsavel->id))
            ->orderBy('data')
            ->orderByRaw('hora is null')
            ->orderBy('hora')
            ->get();

        $vencidas = $tarefas->filter(fn (TarefaNegociacao $tarefa): bool => $tarefa->data->toDateString() < $hoje);
        $deHoje = $tarefas->filter(fn (TarefaNegociacao $tarefa): bool => $tarefa->data->toDateString() === $hoje);

        $grupos = [
            'followUpsPendentes' => $vencidas->filter(fn (TarefaNegociacao $tarefa): bool => $tarefa->tipo === TipoTarefaNegociacao::FollowUp),
            'followUpsHoje' => $deHoje->filter(fn (TarefaNegociacao $tarefa): bool => $tarefa->tipo === TipoTarefaNegociacao::FollowUp),
            'atrasadas' => $vencidas->filter(fn (TarefaNegociacao $tarefa): bool => $tarefa->tipo !== TipoTarefaNegociacao::FollowUp),
            'reunioesHoje' => $deHoje->filter(fn (TarefaNegociacao $tarefa): bool => $tarefa->tipo === TipoTarefaNegociacao::Reuniao),
            'demaisHoje' => $deHoje->filter(fn (TarefaNegociacao $tarefa): bool => ! in_array($tarefa->tipo, [TipoTarefaNegociacao::FollowUp, TipoTarefaNegociacao::Reuniao], true)),
        ];

        $itens = array_map(
            fn (Collection $grupo): array => $grupo->map(fn (TarefaNegociacao $tarefa): array => $this->tarefa($tarefa, $agora))->values()->all(),
            $grupos,
        );

        $semProximoPasso = $this->semProximoPasso($responsavel, $agora);

        return [
            'data' => $hoje,
            ...$itens,
            'semProximoPasso' => $semProximoPasso,
            'totais' => [
                ...array_map(fn (array $grupo): int => count($grupo), $itens),
                'semProximoPasso' => count($semProximoPasso),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function semProximoPasso(?User $responsavel, CarbonInterface $agora): array
    {
        return Negociacao::query()
            ->with([
                'funil:id,nome',
                'etapaFunil:id,nome',
                'empresa:id,nome',
                'contato:id,nome,telefone',
                'responsavel:id,name',
            ])
            ->tap(fn (Builder $query) => $this->somenteAbertas($query))
            ->whereDoesntHave('tarefas', fn (Builder $query) => $query->whereIn('status', StatusTarefaNegociacao::abertos()))
            ->when($responsavel !== null, fn (Builder $query) => $query->where('responsavel_user_id', $responsavel->id))
            ->orderByRaw('ultimo_contato_em is not null')
            ->orderBy('ultimo_contato_em')
            ->orderBy('etapa_desde')
            ->get()
            ->map(fn (Negociacao $negociacao): array => [
                'negociacaoId' => $negociacao->id,
                'assunto' => $negociacao->assunto,
                'lead' => $negociacao->empresa?->nome ?: $negociacao->contato?->nome,
                'contatoNome' => $negociacao->contato?->nome,
                'telefone' => $negociacao->contato?->telefone,
                'funilNome' => $negociacao->funil?->nome,
                'etapaNome' => $negociacao->etapaFunil?->nome,
                'responsavelNome' => $negociacao->responsavel?->name,
                'diasSemContato' => $this->diasSemContato($negociacao, $agora),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function tarefa(TarefaNegociacao $tarefa, CarbonInterface $agora): array
    {
        $negociacao = $tarefa->negociacao;
        $hora = $tarefa->hora?->format('H:i');

        return [
            'id' => $tarefa->id,
            'negociacaoId' => $tarefa->negociacao_id,
            'descricao' => $tarefa->descricao,
            'tipo' => $tarefa->tipo->value,
            'tipoLabel' => $tarefa->tipo->label(),
            'status' => $tarefa->status->value,
            'data' => $tarefa->data->toDateString(),
            'hora' => $hora,
            'diasAtraso' => max(0, (int) $tarefa->data->copy()->startOfDay()->diffInDays($agora->copy()->startOfDay())),
            'horarioPassou' => $hora !== null
                && $tarefa->data->toDateString() === $agora->toDateString()
                && $hora < $agora->format('H:i'),
            'assunto' => $negociacao->assunto,
            'lead' => $negociacao->empresa?->nome ?: $negociacao->contato?->nome,
            'contatoNome' => $negociacao->contato?->nome,
            'telefone' => $negociacao->contato?->telefone,
            'funilNome' => $negociacao->funil?->nome,
            'etapaNome' => $negociacao->etapaFunil?->nome,
            'responsavelNome' => $tarefa->responsavel?->name,
            'diasSemContato' => $this->diasSemContato($negociacao, $agora),
        ];
    }

    private function diasSemContato(Negociacao $negociacao, CarbonInterface $agora): ?int
    {
        return $negociacao->ultimo_contato_em === null
            ? null
            : (int) $negociacao->ultimo_contato_em->copy()->setTimezone($agora->getTimezone())->startOfDay()->diffInDays($agora->copy()->startOfDay());
    }

    /**
     * @param  Builder<Negociacao>  $query
     */
    private function somenteAbertas(Builder $query): void
    {
        $query->whereHas(
            'etapaFunil',
            fn (Builder $etapa) => $etapa->where('resultado', EtapaFunilResultado::Aberta->value),
        );
    }
}
