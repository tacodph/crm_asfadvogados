<?php

namespace App\Http\Resources;

use App\Models\Empresa;
use App\Models\Negociacao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Empresa
 */
class EmpresaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->statusConflito;
        $statusComercial = $this->statusComercial;
        $contatos = $this->contatos;
        $quantidade = $contatos->count();
        $primeiro = $contatos->first();
        $negociacoes = $this->negociacoes;
        $valorAberto = (float) $negociacoes->sum('valor');

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cnpj' => $this->cnpj,
            'setor' => "{$this->setor->nome} · {$this->porte}",
            'uf' => $this->uf->sigla,
            'municipio' => $this->cidade,
            'contatosResumo' => $quantidade === 1
                ? ($primeiro?->nome ?? '1 contato')
                : "{$quantidade} contatos",
            'statusComercial' => $statusComercial->nome,
            'statusComercialBg' => $statusComercial->cor_fundo,
            'statusComercialCor' => $statusComercial->cor_texto,
            'conflito' => $status->nome,
            'conflitoBg' => $status->cor_fundo,
            'conflitoCor' => $status->cor_texto,
            'valorFmt' => NegociacaoResource::moeda($valorAberto),
            'abertas' => $negociacoes->count().' negociação(ões)',
            'drawer' => [
                'nome' => $this->nome,
                'cnpj' => $this->cnpj,
                'cidade' => "{$this->cidade} / {$this->uf->sigla}",
                'campos' => [
                    ['label' => 'Setor', 'valor' => $this->setor->nome],
                    ['label' => 'Porte', 'valor' => $this->porte],
                    ['label' => 'Município', 'valor' => $this->cidade],
                    ['label' => 'UF', 'valor' => $this->uf->sigla],
                    ['label' => 'Status comercial', 'valor' => $statusComercial->nome],
                    ['label' => 'Responsável', 'valor' => $this->responsavel?->name ?: '—'],
                    ['label' => 'Em negociação', 'valor' => NegociacaoResource::moeda($valorAberto)],
                ],
                'conflitoCor' => $status->cor_texto,
                'conflitoBg' => $status->cor_fundo_detalhe,
                'conflitoBorda' => $status->cor_borda_detalhe,
                'conflitoTexto' => $this->conflito_texto ?: '—',
                'contatos' => ContatoResource::collection($contatos)->resolve(),
                'negociacoes' => $negociacoes
                    ->map(fn (Negociacao $negociacao) => NegociacaoResource::resumo($negociacao))
                    ->values()
                    ->all(),
            ],
        ];
    }
}
