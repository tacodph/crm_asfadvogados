<?php

namespace App\Http\Controllers;

use App\Enums\StatusProposta;
use App\Http\Resources\NegociacaoResource;
use App\Http\Resources\PropostaResource;
use App\Models\Proposta;
use Inertia\Inertia;
use Inertia\Response;

class PropostaController extends Controller
{
    public function index(): Response
    {
        $propostas = Proposta::query()
            ->with([
                'contato:id,nome',
                'empresa:id,nome',
            ])
            ->orderByDesc('enviado_em')
            ->orderByDesc('id')
            ->get();

        $abertas = $propostas->filter(
            fn (Proposta $proposta): bool => $proposta->status->emAberto(),
        );

        $ticketMedio = $abertas->isEmpty()
            ? 0.0
            : (float) $abertas->avg(fn (Proposta $proposta): float => (float) $proposta->honorarios);

        $desde = now()->subDays(30);
        $enviadas30d = $propostas->filter(
            fn (Proposta $proposta): bool => $proposta->enviado_em !== null
                && $proposta->enviado_em->greaterThanOrEqualTo($desde),
        );
        $aceitas30d = $enviadas30d->filter(
            fn (Proposta $proposta): bool => $proposta->status === StatusProposta::Aceita,
        );
        $aceitePct = $enviadas30d->isEmpty()
            ? 0
            : (int) round(($aceitas30d->count() / $enviadas30d->count()) * 100);

        return Inertia::render('crm/Propostas', [
            'kpis' => [
                ['label' => 'Em aberto', 'value' => (string) $abertas->count()],
                ['label' => 'Ticket médio', 'value' => NegociacaoResource::moedaCompacta($ticketMedio)],
                ['label' => 'Aceite em 30 dias', 'value' => $aceitePct.'%'],
            ],
            'propostas' => $propostas
                ->map(fn (Proposta $proposta): array => (new PropostaResource($proposta))->resumoLista())
                ->values()
                ->all(),
        ]);
    }

    public function show(Proposta $proposta): Response
    {
        $proposta->load([
            'contato:id,nome,email,telefone,cargo',
            'empresa:id,nome,cnpj,porte',
            'autor:id,name',
            'negociacao.funil:id,nome,slug',
            'negociacao.etapaFunil:id,nome',
            'negociacao.responsavel:id,name',
        ]);

        return Inertia::render('crm/PropostasShow', [
            'proposta' => (new PropostaResource($proposta))->detalhe(),
        ]);
    }
}
