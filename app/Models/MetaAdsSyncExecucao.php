<?php

namespace App\Models;

use App\Enums\MetaAdsSyncStatus;
use App\Enums\MetaAdsSyncTipo;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MetaAdsSyncExecucaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Auditoria de uma rodada de sincronização com o Meta Ads. `status = parcial`
 * significa interrompida por rate limit (não é erro — a próxima rodada completa).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $meta_ads_conta_id
 * @property MetaAdsSyncTipo $tipo
 * @property MetaAdsSyncStatus $status
 * @property Carbon|null $janela_inicio
 * @property Carbon|null $janela_fim
 * @property int $objetos_afetados
 * @property int|null $duracao_ms
 * @property string|null $erro
 * @property Carbon $iniciado_em
 * @property Carbon|null $concluido_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('meta_ads_sync_execucoes')]
#[Fillable([
    'meta_ads_conta_id',
    'tipo',
    'status',
    'janela_inicio',
    'janela_fim',
    'objetos_afetados',
    'duracao_ms',
    'erro',
    'iniciado_em',
    'concluido_em',
])]
class MetaAdsSyncExecucao extends Model
{
    /** @use HasFactory<MetaAdsSyncExecucaoFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => MetaAdsSyncTipo::class,
            'status' => MetaAdsSyncStatus::class,
            'janela_inicio' => 'date',
            'janela_fim' => 'date',
            'objetos_afetados' => 'integer',
            'duracao_ms' => 'integer',
            'iniciado_em' => 'datetime',
            'concluido_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MetaAdsConta, $this>
     */
    public function conta(): BelongsTo
    {
        return $this->belongsTo(MetaAdsConta::class, 'meta_ads_conta_id');
    }
}
