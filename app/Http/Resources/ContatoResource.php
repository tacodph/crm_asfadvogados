<?php

namespace App\Http\Resources;

use App\Models\ConsentimentoContato;
use App\Models\Contato;
use App\Models\Negociacao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Contato
 */
class ContatoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->statusConsentimento;
        $statusComercial = $this->statusComercial;
        $canal = $this->canalContato;
        $empresa = $this->empresa;
        $cargo = $this->cargo ?: '—';
        $contexto = $empresa ? "{$empresa->nome} · {$cargo}" : $cargo;

        $quantidadeNegociacoes = $this->negociacoes->count();

        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cargo' => $this->cargo ?: '—',
            'email' => $this->email ?: '—',
            'telefone' => $this->telefone ?: '—',
            'cpf' => $this->cpf,
            'cnpj' => $this->empresa?->cnpj,
            'uf' => $this->uf?->sigla ?: '—',
            'municipio' => $this->cidade ?: '—',
            'dedupeMesclado' => (bool) $this->registro_mesclado,
            'dedupe' => $this->registro_mesclado
                ? ($this->observacao_deduplicacao ?: 'registro mesclado')
                : ($this->observacao_deduplicacao ?: 'registro único'),
            'contexto' => $contexto,
            'canal' => $canal->nome,
            'statusComercial' => $statusComercial->nome,
            'statusComercialBg' => $statusComercial->cor_fundo,
            'statusComercialCor' => $statusComercial->cor_texto,
            'consent' => $status->nome,
            'consentBg' => $status->cor_fundo,
            'consentCor' => $status->cor_texto,
            'negocios' => $quantidadeNegociacoes === 0
                ? '—'
                : $quantidadeNegociacoes.' aberta(s)',
            'drawer' => [
                'nome' => $this->nome,
                'subtitulo' => $contexto,
                'campos' => [
                    ['label' => 'Telefone', 'valor' => $this->telefone ?: '—'],
                    ['label' => 'E-mail', 'valor' => $this->email ?: '—'],
                    [
                        'label' => 'Cidade / UF',
                        'valor' => $this->cidade && $this->uf
                            ? "{$this->cidade} / {$this->uf->sigla}"
                            : '—',
                    ],
                    ['label' => 'Canal de origem', 'valor' => $canal->nome],
                    ['label' => 'Status comercial', 'valor' => $statusComercial->nome],
                    [
                        'label' => 'Tipo',
                        'valor' => $this->tipoPessoa->slug === 'pj'
                            ? 'Contato de empresa (PJ)'
                            : 'Pessoa física',
                    ],
                ],
                'consentimentos' => $this->consentimentos
                    ->map(fn (ConsentimentoContato $item): array => [
                        'finalidade' => $item->finalidade->slug === 'mensagens'
                            ? "Mensagens por {$canal->nome}"
                            : $item->finalidade->nome,
                        'estado' => $this->rotuloConsentimento($item),
                        'cor' => $item->statusConsentimento->cor_texto,
                    ])
                    ->values()
                    ->all(),
                'negociacoes' => $this->negociacoes
                    ->map(fn (Negociacao $negociacao) => NegociacaoResource::resumo($negociacao))
                    ->values()
                    ->all(),
            ],
        ];
    }

    private function rotuloConsentimento(ConsentimentoContato $item): string
    {
        if ($item->revogado_em !== null) {
            return 'revogado em '.$item->revogado_em->format('d/m');
        }

        if ($item->statusConsentimento->slug === 'pendente') {
            return 'aguardando opt-in';
        }

        if ($item->concedido_em !== null && $item->statusConsentimento->slug === 'opt-in-registrado') {
            return 'opt-in '.$item->concedido_em->format('d/m');
        }

        return $item->statusConsentimento->nome;
    }
}
