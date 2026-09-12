<?php

namespace App\Models;

use App\Enums\MetaConversaoEventoStatus;
use App\Enums\MetaEventName;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MetaConversaoEventoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Log append-only de cada disparo para a API de Conversões. Fonte de verdade
 * dos relatórios (a Meta não expõe leitura dos eventos enviados).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $meta_conversao_config_id
 * @property int|null $negociacao_id
 * @property int|null $contato_id
 * @property MetaEventName $event_name
 * @property string $event_id
 * @property Carbon $event_time
 * @property string $action_source
 * @property MetaConversaoEventoStatus $status
 * @property string|null $motivo_descarte
 * @property int $tentativas
 * @property int|null $http_status
 * @property int|null $events_received
 * @property string|null $fbtrace_id
 * @property string|null $error_code
 * @property string|null $error_message
 * @property array<string, mixed> $request_payload
 * @property array<string, mixed>|null $response_body
 * @property Carbon|null $enviado_em
 * @property bool $is_teste
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('meta_conversao_eventos')]
#[Fillable([
    'meta_conversao_config_id',
    'negociacao_id',
    'contato_id',
    'event_name',
    'event_id',
    'event_time',
    'action_source',
    'status',
    'motivo_descarte',
    'tentativas',
    'http_status',
    'events_received',
    'fbtrace_id',
    'error_code',
    'error_message',
    'request_payload',
    'response_body',
    'enviado_em',
    'is_teste',
])]
class MetaConversaoEvento extends Model
{
    /** @use HasFactory<MetaConversaoEventoFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_name' => MetaEventName::class,
            'status' => MetaConversaoEventoStatus::class,
            'event_time' => 'datetime',
            'enviado_em' => 'datetime',
            'request_payload' => 'array',
            'response_body' => 'array',
            'is_teste' => 'boolean',
            'tentativas' => 'integer',
            'http_status' => 'integer',
            'events_received' => 'integer',
        ];
    }

    /**
     * @param  Builder<MetaConversaoEvento>  $query
     */
    public function scopePendentes(Builder $query): void
    {
        $query->where('status', MetaConversaoEventoStatus::Pendente->value);
    }

    /**
     * @param  Builder<MetaConversaoEvento>  $query
     */
    public function scopeComErro(Builder $query): void
    {
        $query->where('status', MetaConversaoEventoStatus::Erro->value);
    }

    /**
     * @return BelongsTo<MetaConversaoConfig, $this>
     */
    public function config(): BelongsTo
    {
        return $this->belongsTo(MetaConversaoConfig::class, 'meta_conversao_config_id');
    }

    /**
     * @return BelongsTo<Negociacao, $this>
     */
    public function negociacao(): BelongsTo
    {
        return $this->belongsTo(Negociacao::class);
    }

    /**
     * @return BelongsTo<Contato, $this>
     */
    public function contato(): BelongsTo
    {
        return $this->belongsTo(Contato::class);
    }
}
