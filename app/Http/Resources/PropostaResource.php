<?php

namespace App\Http\Resources;

use App\Enums\StatusProposta;
use App\Models\Proposta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Proposta
 */
class PropostaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resumoLista();
    }

    /**
     * @return array<string, mixed>
     */
    public function resumoLista(): array
    {
        $status = $this->status instanceof StatusProposta
            ? $this->status
            : StatusProposta::from((string) $this->status);
        $cores = $status->cores();
        $cliente = $this->empresa?->nome ?: $this->contato->nome;

        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'cliente' => $cliente,
            'contato' => $this->contato->nome,
            'empresa' => $this->empresa?->nome,
            'versao' => 'v'.$this->versao.' · '.$this->idadeVersao(),
            'versaoNumero' => $this->versao,
            'status' => $status->label(),
            'statusValor' => $status->value,
            'statusBg' => $cores['fundo'],
            'statusCor' => $cores['texto'],
            'prazo' => $this->rotuloValidade($status),
            'prazoCor' => $this->corValidade($status),
            'valor' => NegociacaoResource::moeda((float) $this->honorarios),
            'honorarios' => (float) $this->honorarios,
            'titulo' => $this->titulo,
            'negociacaoId' => $this->negociacao_id,
            'validoAte' => $this->valido_ate->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detalhe(): array
    {
        $base = $this->resumoLista();
        $negociacao = $this->negociacao;

        return [
            ...$base,
            'escopo' => $this->escopo,
            'parcelamento' => $this->parcelamento,
            'indiceReajuste' => $this->indice_reajuste,
            'modeloOrigem' => $this->modelo_origem,
            'autor' => $this->autor?->name,
            'enviadoEm' => $this->enviado_em?->toIso8601String(),
            'aceitoEm' => $this->aceito_em?->toIso8601String(),
            'clausulas' => $this->clausulas ?? [],
            'negociacao' => [
                'id' => $negociacao->id,
                'assunto' => $negociacao->assunto,
                'funil' => $negociacao->funil->slug === 'b2b'
                    ? 'B2B consultivo'
                    : $negociacao->funil->nome,
                'etapa' => $negociacao->etapaFunil->nome,
                'responsavel' => $negociacao->responsavel->name,
                'valorFmt' => NegociacaoResource::moeda((float) $negociacao->valor),
            ],
            'contatoDetalhe' => [
                'id' => $this->contato->id,
                'nome' => $this->contato->nome,
                'email' => $this->contato->email,
                'telefone' => $this->contato->telefone,
                'cargo' => $this->contato->cargo,
            ],
            'empresaDetalhe' => $this->empresa === null ? null : [
                'id' => $this->empresa->id,
                'nome' => $this->empresa->nome,
                'cnpj' => $this->empresa->cnpj,
                'porte' => $this->empresa->porte,
            ],
        ];
    }

    private function idadeVersao(): string
    {
        $referencia = $this->enviado_em ?? $this->updated_at ?? now();
        $dias = (int) Carbon::parse($referencia)->startOfDay()->diffInDays(now()->startOfDay());

        if ($dias === 0) {
            return 'hoje';
        }

        if ($dias === 1) {
            return 'há 1 dia';
        }

        return 'há '.$dias.' dias';
    }

    private function rotuloValidade(StatusProposta $status): string
    {
        if ($status === StatusProposta::Aceita) {
            return 'contrato em vigor';
        }

        if ($status === StatusProposta::Expirada || $this->valido_ate->isPast()) {
            $dias = (int) $this->valido_ate->startOfDay()->diffInDays(now()->startOfDay());

            return $dias <= 0 ? 'expirada hoje' : 'expirada há '.$dias.' '.($dias === 1 ? 'dia' : 'dias');
        }

        $dias = (int) now()->startOfDay()->diffInDays($this->valido_ate->startOfDay());

        if ($dias === 0) {
            return 'vence hoje';
        }

        if ($dias === 1) {
            return 'vence amanhã';
        }

        return 'vence em '.$dias.' dias';
    }

    private function corValidade(StatusProposta $status): string
    {
        if ($status === StatusProposta::Expirada || $this->valido_ate->isPast()) {
            return '#9B3B2F';
        }

        $dias = (int) now()->startOfDay()->diffInDays($this->valido_ate->startOfDay());

        return $dias <= 1 ? '#9B3B2F' : '#3C4450';
    }
}
