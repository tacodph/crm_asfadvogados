<?php

namespace App\Models;

use App\Enums\EtapaFunilResultado;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EtapaFunilFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $funil_id
 * @property string $nome
 * @property string $sla
 * @property list<string> $campos
 * @property string|null $meta_evento evento CAPI disparado ao entrar na etapa (MetaEventName::value)
 * @property bool $exige_motivo
 * @property EtapaFunilResultado $resultado
 * @property int $ordem
 * @property string $cor_fundo
 * @property string $cor_texto
 * @property string $cor_suave
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('etapas_funil')]
#[Fillable([
    'funil_id',
    'nome',
    'sla',
    'campos',
    'meta_evento',
    'exige_motivo',
    'resultado',
    'ordem',
    'cor_fundo',
    'cor_texto',
    'cor_suave',
])]
class EtapaFunil extends Model
{
    /** @use HasFactory<EtapaFunilFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'exige_motivo' => false,
        'resultado' => 'aberta',
        'ordem' => 1,
        'cor_texto' => '#FBF9F4',
        'cor_suave' => 'rgba(251,249,244,0.9)',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'campos' => 'array',
            'exige_motivo' => 'boolean',
            'resultado' => EtapaFunilResultado::class,
            'ordem' => 'integer',
        ];
    }

    public function isGanho(): bool
    {
        return $this->resultado === EtapaFunilResultado::Ganho;
    }

    public function isPerdido(): bool
    {
        return $this->resultado === EtapaFunilResultado::Perdido;
    }

    public function isTerminal(): bool
    {
        return $this->resultado->isTerminal();
    }

    /**
     * @return BelongsTo<Funil, $this>
     */
    public function funil(): BelongsTo
    {
        return $this->belongsTo(Funil::class);
    }

    /**
     * @return HasMany<Negociacao, $this>
     */
    public function negociacoes(): HasMany
    {
        return $this->hasMany(Negociacao::class);
    }
}
