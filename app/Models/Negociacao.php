<?php

namespace App\Models;

use Database\Factories\NegociacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $funil_id
 * @property int $etapa_funil_id
 * @property int|null $empresa_id
 * @property int $contato_id
 * @property int $canal_contato_id
 * @property int $responsavel_user_id
 * @property string $assunto
 * @property string $valor
 * @property Carbon|null $previsao_fechamento
 * @property Carbon $etapa_desde
 * @property string|null $proxima_tarefa
 * @property Carbon|null $proxima_tarefa_em
 * @property Carbon|null $proxima_tarefa_hora
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('negociacoes')]
#[Fillable([
    'funil_id',
    'etapa_funil_id',
    'empresa_id',
    'contato_id',
    'canal_contato_id',
    'responsavel_user_id',
    'assunto',
    'valor',
    'previsao_fechamento',
    'etapa_desde',
    'proxima_tarefa',
    'proxima_tarefa_em',
    'proxima_tarefa_hora',
])]
class Negociacao extends Model
{
    /** @use HasFactory<NegociacaoFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'previsao_fechamento' => 'date',
            'etapa_desde' => 'datetime',
            'proxima_tarefa_em' => 'date',
            'proxima_tarefa_hora' => 'datetime:H:i',
        ];
    }

    /**
     * @return BelongsTo<Funil, $this>
     */
    public function funil(): BelongsTo
    {
        return $this->belongsTo(Funil::class);
    }

    /**
     * @return BelongsTo<EtapaFunil, $this>
     */
    public function etapaFunil(): BelongsTo
    {
        return $this->belongsTo(EtapaFunil::class);
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<Contato, $this>
     */
    public function contato(): BelongsTo
    {
        return $this->belongsTo(Contato::class);
    }

    /**
     * @return BelongsTo<CanalContato, $this>
     */
    public function canalContato(): BelongsTo
    {
        return $this->belongsTo(CanalContato::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    /**
     * @return HasMany<HistoricoNegociacao, $this>
     */
    public function historicos(): HasMany
    {
        return $this->hasMany(HistoricoNegociacao::class)->orderBy('ocorrido_em');
    }
}
