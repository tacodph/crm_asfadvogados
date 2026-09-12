<?php

namespace App\Models;

use App\Enums\StatusProposta;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\PropostaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $negociacao_id
 * @property int $contato_id
 * @property int|null $empresa_id
 * @property int|null $autor_user_id
 * @property string $codigo
 * @property int $versao
 * @property string $titulo
 * @property string $escopo
 * @property string $honorarios
 * @property string|null $parcelamento
 * @property string|null $indice_reajuste
 * @property string|null $modelo_origem
 * @property StatusProposta $status
 * @property Carbon $valido_ate
 * @property Carbon|null $enviado_em
 * @property Carbon|null $aceito_em
 * @property list<array{titulo: string, texto: string}>|null $clausulas
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('propostas')]
#[Fillable([
    'negociacao_id',
    'contato_id',
    'empresa_id',
    'autor_user_id',
    'codigo',
    'versao',
    'titulo',
    'escopo',
    'honorarios',
    'parcelamento',
    'indice_reajuste',
    'modelo_origem',
    'status',
    'valido_ate',
    'enviado_em',
    'aceito_em',
    'clausulas',
])]
class Proposta extends Model
{
    /** @use HasFactory<PropostaFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'versao' => 1,
        'status' => StatusProposta::Rascunho->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'versao' => 'integer',
            'honorarios' => 'decimal:2',
            'status' => StatusProposta::class,
            'valido_ate' => 'date',
            'enviado_em' => 'datetime',
            'aceito_em' => 'datetime',
            'clausulas' => 'array',
        ];
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

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_user_id');
    }
}
