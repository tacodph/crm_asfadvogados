<?php

namespace App\Models;

use App\Enums\MetaAdsStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MetaAdsConjuntoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Conjunto de anúncios (adset) espelhado localmente (upsert por `meta_adset_id`).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $meta_ads_conta_id
 * @property int $meta_ads_campanha_id
 * @property string $meta_adset_id
 * @property string $nome
 * @property string|null $optimization_goal
 * @property MetaAdsStatus $status
 * @property string|null $effective_status
 * @property int|null $orcamento_diario_centavos
 * @property int|null $orcamento_total_centavos
 * @property array<string, mixed> $bruto
 * @property Carbon $sincronizado_em
 * @property Carbon|null $arquivado_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('meta_ads_conjuntos')]
#[Fillable([
    'meta_ads_conta_id',
    'meta_ads_campanha_id',
    'meta_adset_id',
    'nome',
    'optimization_goal',
    'status',
    'effective_status',
    'orcamento_diario_centavos',
    'orcamento_total_centavos',
    'bruto',
    'sincronizado_em',
    'arquivado_em',
])]
class MetaAdsConjunto extends Model
{
    /** @use HasFactory<MetaAdsConjuntoFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => MetaAdsStatus::class,
            'orcamento_diario_centavos' => 'integer',
            'orcamento_total_centavos' => 'integer',
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
     * @param  Builder<MetaAdsConjunto>  $query
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
     * @return HasMany<MetaAdsAnuncio, $this>
     */
    public function anuncios(): HasMany
    {
        return $this->hasMany(MetaAdsAnuncio::class, 'meta_ads_conjunto_id');
    }
}
