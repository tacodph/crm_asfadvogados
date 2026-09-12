<?php

namespace App\Support\Meta;

use App\Models\ConsentimentoContato;
use App\Models\MetaConversaoConfig;
use App\Models\Negociacao;
use Illuminate\Support\Collection;

/**
 * "O evento da API de Conversões seria enviado agora?" — a mesma regra do gate
 * de RegistrarEventoConversaoMeta, exposta para a UI (badge no funil) sem
 * duplicar a lógica de consentimento.
 *
 * Bound como singleton: os catálogos são lidos uma vez por request (o funil
 * renderiza dezenas de negociações).
 * ponytail: cache request-scoped no singleton; se um dia rodar sob Octane,
 * resetar $configsAtivas entre requests.
 */
final class ElegibilidadeEventoMeta
{
    /** @var Collection<int, MetaConversaoConfig>|null */
    private ?Collection $configsAtivas = null;

    public function __construct(private readonly ResolverCampanhaConversao $resolver) {}

    /**
     * @return 'enviavel'|'sem_consentimento'|'campanha_indefinida'|'desligado'
     */
    public function estado(Negociacao $negociacao): string
    {
        $ativas = $this->configsAtivas();

        if ($ativas->isEmpty()) {
            return 'desligado';
        }

        $config = ($this->resolver)($negociacao, null, $ativas);

        if (! $config instanceof MetaConversaoConfig || ! $config->ativo) {
            return 'campanha_indefinida';
        }

        return $this->temConsentimento($negociacao, $config) ? 'enviavel' : 'sem_consentimento';
    }

    public function temConsentimento(Negociacao $negociacao, MetaConversaoConfig $config): bool
    {
        $finalidade = $config->finalidade_consentimento_slug;

        if ($finalidade === null || $finalidade === '') {
            return false; // fail-closed
        }

        $slugsValidos = config('meta.capi.slugs_consentimento_valido');
        $slugsValidos = is_array($slugsValidos) && $slugsValidos !== []
            ? $slugsValidos
            : ['opt-in-registrado', 'vigente'];

        // Em memória quando a relação já veio carregada (tela do funil).
        if ($negociacao->contato->relationLoaded('consentimentos')) {
            return $negociacao->contato->consentimentos->contains(
                fn (ConsentimentoContato $c): bool => $c->revogado_em === null
                    && $c->finalidade->slug === $finalidade
                    && in_array($c->statusConsentimento->slug, $slugsValidos, true),
            );
        }

        return ConsentimentoContato::query()
            ->where('contato_id', $negociacao->contato_id)
            ->whereNull('revogado_em')
            ->whereHas('finalidade', fn ($q) => $q->where('slug', $finalidade))
            ->whereHas('statusConsentimento', fn ($q) => $q->whereIn('slug', $slugsValidos))
            ->exists();
    }

    /**
     * @return Collection<int, MetaConversaoConfig>
     */
    private function configsAtivas(): Collection
    {
        return $this->configsAtivas ??= MetaConversaoConfig::query()->ativas()->get();
    }
}
