<?php

namespace App\Models;

use App\Enums\MetaAdsNivel;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MetaAdsInsightDiarioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Snapshot diário de um objeto de anúncio (hoje sempre nível `anuncio`).
 * Dinheiro em centavos; `objeto_id` é o id da Meta (`meta_ad_id`).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $meta_ads_conta_id
 * @property MetaAdsNivel $nivel
 * @property string $objeto_id
 * @property Carbon $referencia
 * @property int $investimento_centavos
 * @property int $impressoes
 * @property int $cliques
 * @property int $cliques_link
 * @property int $alcance
 * @property int|null $cpc_centavos
 * @property int|null $cpm_centavos
 * @property int|null $custo_por_resultado_centavos
 * @property string|null $ctr
 * @property string|null $frequencia
 * @property int $resultados
 * @property array<int, array<string, mixed>>|null $acoes
 * @property array<string, mixed> $bruto
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('meta_ads_insights_diarios')]
#[Fillable([
    'meta_ads_conta_id',
    'nivel',
    'objeto_id',
    'referencia',
    'investimento_centavos',
    'impressoes',
    'cliques',
    'cliques_link',
    'alcance',
    'cpc_centavos',
    'cpm_centavos',
    'custo_por_resultado_centavos',
    'ctr',
    'frequencia',
    'resultados',
    'acoes',
    'bruto',
])]
class MetaAdsInsightDiario extends Model
{
    /** @use HasFactory<MetaAdsInsightDiarioFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'nivel' => MetaAdsNivel::class,
            'referencia' => 'date',
            'investimento_centavos' => 'integer',
            'impressoes' => 'integer',
            'cliques' => 'integer',
            'cliques_link' => 'integer',
            'alcance' => 'integer',
            'cpc_centavos' => 'integer',
            'cpm_centavos' => 'integer',
            'custo_por_resultado_centavos' => 'integer',
            'ctr' => 'decimal:4',
            'frequencia' => 'decimal:2',
            'resultados' => 'integer',
            'acoes' => 'array',
            'bruto' => 'array',
        ];
    }

    /**
     * Investimento do dia em unidades da moeda da conta (só leitura/exibição —
     * a escrita é sempre em centavos).
     */
    public function investimentoReais(): float
    {
        return $this->investimento_centavos / 100;
    }

    /**
     * @param  Builder<MetaAdsInsightDiario>  $query
     */
    public function scopeNoIntervalo(Builder $query, \DateTimeInterface|string $de, \DateTimeInterface|string $ate): void
    {
        $query->whereBetween('referencia', [
            $de instanceof \DateTimeInterface ? $de->format('Y-m-d') : $de,
            $ate instanceof \DateTimeInterface ? $ate->format('Y-m-d') : $ate,
        ]);
    }

    /**
     * @param  Builder<MetaAdsInsightDiario>  $query
     */
    public function scopeNivel(Builder $query, MetaAdsNivel $nivel): void
    {
        $query->where('nivel', $nivel->value);
    }

    /**
     * @return BelongsTo<MetaAdsConta, $this>
     */
    public function conta(): BelongsTo
    {
        return $this->belongsTo(MetaAdsConta::class, 'meta_ads_conta_id');
    }
}
