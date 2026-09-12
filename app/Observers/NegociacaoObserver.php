<?php

namespace App\Observers;

use App\Actions\Meta\AtribuirNegociacaoAnuncioMeta;
use App\Actions\Meta\RegistrarEventoConversaoMeta;
use App\Enums\MetaEventName;
use App\Models\EtapaFunil;
use App\Models\Negociacao;
use Throwable;

/**
 * Traduz fatos do ciclo de vida da negociação em eventos da API de
 * Conversões da Meta. Nunca propaga exceção — um problema na integração
 * não pode impedir o save da negociação.
 */
class NegociacaoObserver
{
    public function created(Negociacao $negociacao): void
    {
        $this->atribuirAnuncio($negociacao);
        $this->disparar($negociacao, MetaEventName::Lead);
    }

    private function atribuirAnuncio(Negociacao $negociacao): void
    {
        try {
            app(AtribuirNegociacaoAnuncioMeta::class)($negociacao);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function updated(Negociacao $negociacao): void
    {
        if (! $negociacao->wasChanged('etapa_funil_id')) {
            return;
        }

        // Leitura fresca: a relação pode estar em cache apontando para a etapa
        // anterior (o controller carrega `etapaFunil` antes do update).
        $etapa = $negociacao->etapaFunil()->first();
        $evento = $this->eventoParaEtapa($etapa);

        if ($evento !== null) {
            $this->disparar($negociacao, $evento);
        }
    }

    private function eventoParaEtapa(?EtapaFunil $etapa): ?MetaEventName
    {
        if ($etapa === null) {
            return null;
        }

        // Coluna explícita da etapa vence (configurável no admin, por tenant).
        if ($etapa->meta_evento !== null && $etapa->meta_evento !== '') {
            return MetaEventName::tryFrom($etapa->meta_evento);
        }

        // Legado: casamento por trecho do nome via config.
        $nome = mb_strtolower($etapa->nome);
        $mapa = config('meta.capi.mapa_etapa_evento');
        $mapa = is_array($mapa) ? $mapa : [];

        foreach ($mapa as $trecho => $evento) {
            if (str_contains($nome, mb_strtolower((string) $trecho))) {
                return MetaEventName::tryFrom((string) $evento);
            }
        }

        return null;
    }

    private function disparar(Negociacao $negociacao, MetaEventName $evento): void
    {
        try {
            app(RegistrarEventoConversaoMeta::class)($negociacao, $evento);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
