<?php

namespace App\Support\Meta;

use App\Models\MetaConversaoConfig;
use App\Models\Negociacao;
use Illuminate\Support\Collection;

/**
 * Decide qual campanha (`MetaConversaoConfig`) recebe o evento de uma
 * negociação: mapa de config → heurística por nome do funil/canal → única
 * campanha ativa. Retorna `null` quando não há como decidir (a Action trata
 * "sem campanha" vs. "campanha indefinida").
 */
class ResolverCampanhaConversao
{
    /**
     * @param  Collection<int, MetaConversaoConfig>|null  $ativas  campanhas ativas já carregadas (evita N+1 no funil)
     */
    public function __invoke(Negociacao $negociacao, ?string $slugForcado = null, ?Collection $ativas = null): ?MetaConversaoConfig
    {
        if ($slugForcado !== null && $slugForcado !== '') {
            return MetaConversaoConfig::query()->where('slug', $slugForcado)->first();
        }

        $ativas ??= MetaConversaoConfig::query()->ativas()->get();

        if ($ativas->isEmpty()) {
            return null;
        }

        $alvo = mb_strtolower(trim($negociacao->funil->nome.' '.$negociacao->canalContato->nome));

        foreach ($this->mapa() as $trecho => $slug) {
            if ($trecho !== '' && str_contains($alvo, $trecho)) {
                $match = $ativas->firstWhere('slug', $slug);

                if ($match instanceof MetaConversaoConfig) {
                    return $match;
                }
            }
        }

        return $ativas->count() === 1 ? $ativas->first() : null;
    }

    /**
     * Mapa "trecho do nome" => "slug da campanha": o de config primeiro, depois
     * a heurística padrão.
     *
     * @return array<string, string>
     */
    private function mapa(): array
    {
        $config = config('meta.capi.mapa_campanha');
        $config = is_array($config) ? $config : [];

        $normalizado = [];

        foreach ($config as $trecho => $slug) {
            $normalizado[mb_strtolower((string) $trecho)] = (string) $slug;
        }

        return $normalizado + [
            'bancár' => 'bancario',
            'bancar' => 'bancario',
            'concurso' => 'concurso',
        ];
    }
}
