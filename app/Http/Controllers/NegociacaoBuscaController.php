<?php

namespace App\Http\Controllers;

use App\Http\Requests\BuscarNegociacoesRequest;
use App\Models\Negociacao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * Pesquisa de negociações em todos os funis e etapas, por nome (contato ou
 * empresa), assunto, telefone, responsável, etapa e status.
 */
class NegociacaoBuscaController extends Controller
{
    private const LIMITE = 20;

    /**
     * Telefones podem estar gravados com máscara; a comparação usa só os dígitos.
     */
    private const TELEFONE_SO_DIGITOS = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contatos.telefone, ' ', ''), '(', ''), ')', ''), '-', ''), '.', ''), '+', '')";

    public function __invoke(BuscarNegociacoesRequest $request): JsonResponse
    {
        $dados = $request->validated();
        $termo = trim((string) ($dados['q'] ?? ''));
        $digitos = preg_replace('/\D+/', '', $termo) ?? '';
        $temFiltro = collect($dados)->except('q')->filter(fn ($valor): bool => $valor !== null)->isNotEmpty();

        if (mb_strlen($termo) < 2 && ! $temFiltro) {
            return response()->json(['resultados' => [], 'total' => 0]);
        }

        $query = Negociacao::query()
            ->when($termo !== '', function (Builder $query) use ($termo, $digitos): void {
                // "!" como caractere de escape: funciona igual no MySQL e no SQLite.
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($termo)).'%';

                $query->where(function (Builder $query) use ($like, $digitos): void {
                    $query->whereRaw("LOWER(assunto) LIKE ? ESCAPE '!'", [$like])
                        ->orWhereHas('contato', fn (Builder $contato) => $contato->where(
                            fn (Builder $campos) => $campos->whereRaw("LOWER(nome) LIKE ? ESCAPE '!'", [$like])
                                ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$like]),
                        ))
                        ->orWhereHas('empresa', fn (Builder $empresa) => $empresa->whereRaw("LOWER(nome) LIKE ? ESCAPE '!'", [$like]))
                        ->when(strlen($digitos) >= 4, fn (Builder $query) => $query->orWhereHas(
                            'contato',
                            fn (Builder $contato) => $contato->whereRaw(self::TELEFONE_SO_DIGITOS.' LIKE ?', ['%'.$digitos.'%']),
                        ));
                });
            })
            ->when($dados['responsavel_id'] ?? null, fn (Builder $query, int $id) => $query->where('responsavel_user_id', $id))
            ->when($dados['etapa_id'] ?? null, fn (Builder $query, int $id) => $query->where('etapa_funil_id', $id))
            ->when($dados['resultado'] ?? null, fn (Builder $query, string $resultado) => $query->whereHas(
                'etapaFunil',
                fn (Builder $etapa) => $etapa->where('resultado', $resultado),
            ))
            ->when($dados['status_atendimento_id'] ?? null, fn (Builder $query, int $id) => $query->where('status_atendimento_id', $id))
            ->when($dados['status_qualificacao_id'] ?? null, fn (Builder $query, int $id) => $query->where('status_qualificacao_id', $id));

        $total = (clone $query)->count();

        $resultados = $query
            ->with([
                'funil:id,nome,slug',
                'etapaFunil:id,nome,cor_fundo,cor_texto,resultado',
                'empresa:id,nome',
                'contato:id,nome,telefone',
                'responsavel:id,name',
                'statusAtendimento:id,nome',
                'statusQualificacao:id,nome',
            ])
            ->orderByDesc('updated_at')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn (Negociacao $negociacao): array => [
                'id' => $negociacao->id,
                'assunto' => $negociacao->assunto,
                'nome' => $negociacao->empresa?->nome ?: $negociacao->contato?->nome,
                'contatoNome' => $negociacao->contato?->nome,
                'telefone' => $negociacao->contato?->telefone,
                'funilId' => $negociacao->funil_id,
                'funilNome' => $negociacao->funil->slug === 'b2b' ? 'B2B consultivo' : $negociacao->funil->nome,
                'etapaId' => $negociacao->etapa_funil_id,
                'etapaNome' => $negociacao->etapaFunil->nome,
                'etapaCorFundo' => $negociacao->etapaFunil->cor_fundo,
                'etapaCorTexto' => $negociacao->etapaFunil->cor_texto,
                'resultado' => $negociacao->etapaFunil->resultado->value,
                'responsavel' => $negociacao->responsavel?->name,
                'statusAtendimento' => $negociacao->statusAtendimento?->nome,
                'statusQualificacao' => $negociacao->statusQualificacao?->nome,
                'proximaTarefa' => $negociacao->proxima_tarefa,
            ])
            ->values()
            ->all();

        return response()->json([
            'resultados' => $resultados,
            'total' => $total,
        ]);
    }
}
