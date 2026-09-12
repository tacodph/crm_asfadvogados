<?php

namespace App\Models;

use App\Enums\MetaAdsObjetivo;
use App\Enums\MetaAdsStatus;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MetaAdsCampanhaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Campanha do Meta Ads espelhada localmente (upsert por `meta_campaign_id`).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $meta_ads_conta_id
 * @property string $meta_campaign_id
 * @property string $nome
 * @property MetaAdsObjetivo $objetivo
 * @property MetaAdsStatus $status
 * @property string|null $effective_status
 * @property int|null $orcamento_diario_centavos
 * @property int|null $orcamento_total_centavos
 * @property Carbon|null $inicio_em
 * @property Carbon|null $fim_em
 * @property array<string, mixed> $bruto
 * @property Carbon $sincronizado_em
 * @property Carbon|null $arquivado_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('meta_ads_campanhas')]
#[Fillable([
    'meta_ads_conta_id',
    'meta_campaign_id',
    'nome',
    'objetivo',
    'status',
    'effective_status',
    'orcamento_diario_centavos',
    'orcamento_total_centavos',
    'inicio_em',
    'fim_em',
    'bruto',
    'sincronizado_em',
    'arquivado_em',
])]
class MetaAdsCampanha extends Model
{
    /** @use HasFactory<MetaAdsCampanhaFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'objetivo' => MetaAdsObjetivo::class,
            'status' => MetaAdsStatus::class,
            'orcamento_diario_centavos' => 'integer',
            'orcamento_total_centavos' => 'integer',
            'inicio_em' => 'datetime',
            'fim_em' => 'datetime',
            'bruto' => 'array',
            'sincronizado_em' => 'datetime',
            'arquivado_em' => 'datetime',
        ];
    }

    /**
     * A Meta está entregando este objeto agora? (`effective_status` é string crua.)
     */
    public function entregando(): bool
    {
        return $this->effective_status === 'ACTIVE';
    }

    /**
     * @param  Builder<MetaAdsCampanha>  $query
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
     * @return HasMany<MetaAdsConjunto, $this>
     */
    public function conjuntos(): HasMany
    {
        return $this->hasMany(MetaAdsConjunto::class, 'meta_ads_campanha_id');
    }

    /**
     * @return HasMany<MetaAdsAnuncio, $this>
     */
    public function anuncios(): HasMany
    {
        return $this->hasMany(MetaAdsAnuncio::class, 'meta_ads_campanha_id');
    }
}
