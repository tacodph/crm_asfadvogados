<?php

namespace App\Models;

use App\Enums\MetaAdsNivel;
use App\Enums\MetaAdsStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MetaAdsAnuncioFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Anúncio (ad) espelhado localmente (upsert por `meta_ad_id`). É o nível em que
 * os insights diários são gravados (`meta_ads_insights_diarios.objeto_id`).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $meta_ads_conta_id
 * @property int $meta_ads_campanha_id
 * @property int $meta_ads_conjunto_id
 * @property string $meta_ad_id
 * @property string $nome
 * @property MetaAdsStatus $status
 * @property string|null $effective_status
 * @property array<string, mixed>|null $criativo_resumo
 * @property array<string, mixed> $bruto
 * @property Carbon $sincronizado_em
 * @property Carbon|null $arquivado_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('meta_ads_anuncios')]
#[Fillable([
    'meta_ads_conta_id',
    'meta_ads_campanha_id',
    'meta_ads_conjunto_id',
    'meta_ad_id',
    'nome',
    'status',
    'effective_status',
    'criativo_resumo',
    'bruto',
    'sincronizado_em',
    'arquivado_em',
])]
class MetaAdsAnuncio extends Model
{
    /** @use HasFactory<MetaAdsAnuncioFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MetaAdsStatus::class,
            'criativo_resumo' => 'array',
            'bruto' => 'array',
            'sincronizado_em' => 'datetime',
            'arquivado_em' => 'datetime',
        ];
    }

    public function entregando(): bool
    {
        return $this->effective_status === 'ACTIVE';
    }

    /**
     * @param  Builder<MetaAdsAnuncio>  $query
     */
    public function scopeVigentes(Builder $query): void
    {
        $query->whereNull('arquivado_em');
    }

    /**
     * @return BelongsTo<MetaAdsConta, $this>
     */
    public function conta(): BelongsTo
    {
        return $this->belongsTo(MetaAdsConta::class, 'meta_ads_conta_id');
    }

    /**
     * @return BelongsTo<MetaAdsCampanha, $this>
     */
    public function campanha(): BelongsTo
    {
        return $this->belongsTo(MetaAdsCampanha::class, 'meta_ads_campanha_id');
    }

    /**
     * @return BelongsTo<MetaAdsConjunto, $this>
     */
    public function conjunto(): BelongsTo
    {
        return $this->belongsTo(MetaAdsConjunto::class, 'meta_ads_conjunto_id');
    }

    /**
     * Insights diários deste anúncio — ligados por `objeto_id` = `meta_ad_id`.
     *
     * @return HasMany<MetaAdsInsightDiario, $this>
     */
    public function insightsDiarios(): HasMany
    {
        return $this->hasMany(MetaAdsInsightDiario::class, 'objeto_id', 'meta_ad_id')
            ->where('nivel', MetaAdsNivel::Anuncio->value);
    }
}
