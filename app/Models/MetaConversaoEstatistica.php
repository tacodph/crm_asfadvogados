<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MetaConversaoEstatisticaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Snapshot diário, por campanha, dos números lidos da Meta (best-effort:
 * `last_fired_time` do Pixel e agregações de `/stats`).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $meta_conversao_config_id
 * @property Carbon $referencia
 * @property Carbon|null $pixel_last_fired_at
 * @property int|null $eventos_servidor
 * @property int|null $eventos_navegador
 * @property int|null $eventos_deduplicados
 * @property string|null $match_rate
 * @property array<string, mixed> $payload_bruto
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('meta_conversao_estatisticas')]
#[Fillable([
    'meta_conversao_config_id',
    'referencia',
    'pixel_last_fired_at',
    'eventos_servidor',
    'eventos_navegador',
    'eventos_deduplicados',
    'match_rate',
    'payload_bruto',
])]
class MetaConversaoEstatistica extends Model
{
    /** @use HasFactory<MetaConversaoEstatisticaFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'referencia' => 'date',
            'pixel_last_fired_at' => 'datetime',
            'eventos_servidor' => 'integer',
            'eventos_navegador' => 'integer',
            'eventos_deduplicados' => 'integer',
            'match_rate' => 'decimal:2',
            'payload_bruto' => 'array',
        ];
    }

    /**
     * @return BelongsTo<MetaConversaoConfig, $this>
     */
    public function config(): BelongsTo
    {
        return $this->belongsTo(MetaConversaoConfig::class, 'meta_conversao_config_id');
    }
}
