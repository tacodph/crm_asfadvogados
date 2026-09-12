<?php

namespace App\Support\Meta;

/**
 * Investimento no Meta Ads × resultados do CRM para um objeto (campanha,
 * conjunto ou anúncio) num intervalo. Todo valor monetário em centavos; os
 * derivados (`cpl*`, `custo*`, `cpm`) e o `roas` são calculados aqui.
 */
final readonly class MetricaInvestimento
{
    public function __construct(
        public string $nivel,
        public int $objetoId,
        public ?string $metaId,
        public string $nome,
        public ?string $effectiveStatus,
        public int $investidoCentavos,
        public int $impressoes,
        public int $cliques,
        public int $cliquesLink,
        public int $alcance,
        public int $leadsMeta,
        public int $leadsCrm,
        public int $reunioes,
        public int $contratos,
        public int $receitaCentavos,
    ) {}

    public function cpmCentavos(): int
    {
        return $this->impressoes > 0
            ? (int) round($this->investidoCentavos / $this->impressoes * 1000)
            : 0;
    }

    public function ctr(): float
    {
        return $this->impressoes > 0
            ? round($this->cliquesLink / $this->impressoes * 100, 2)
            : 0.0;
    }

    public function cplMetaCentavos(): int
    {
        return (int) round($this->investidoCentavos / max($this->leadsMeta, 1));
    }

    public function cplCrmCentavos(): int
    {
        return (int) round($this->investidoCentavos / max($this->leadsCrm, 1));
    }

    public function custoReuniaoCentavos(): int
    {
        return (int) round($this->investidoCentavos / max($this->reunioes, 1));
    }

    public function custoContratoCentavos(): int
    {
        return (int) round($this->investidoCentavos / max($this->contratos, 1));
    }

    public function roas(): float
    {
        return $this->investidoCentavos > 0
            ? round($this->receitaCentavos / $this->investidoCentavos, 2)
            : 0.0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nivel' => $this->nivel,
            'objeto_id' => $this->objetoId,
            'meta_id' => $this->metaId,
            'nome' => $this->nome,
            'effective_status' => $this->effectiveStatus,
            'investido_centavos' => $this->investidoCentavos,
            'impressoes' => $this->impressoes,
            'cliques' => $this->cliques,
            'cliques_link' => $this->cliquesLink,
            'alcance' => $this->alcance,
            'cpm_centavos' => $this->cpmCentavos(),
            'ctr' => $this->ctr(),
            'leads_meta' => $this->leadsMeta,
            'leads_crm' => $this->leadsCrm,
            'cpl_meta_centavos' => $this->cplMetaCentavos(),
            'cpl_crm_centavos' => $this->cplCrmCentavos(),
            'reunioes' => $this->reunioes,
            'custo_reuniao_centavos' => $this->custoReuniaoCentavos(),
            'contratos' => $this->contratos,
            'custo_contrato_centavos' => $this->custoContratoCentavos(),
            'receita_centavos' => $this->receitaCentavos,
            'roas' => $this->roas(),
        ];
    }
}
