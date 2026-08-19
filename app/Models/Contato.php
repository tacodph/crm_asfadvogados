<?php

namespace App\Models;

use Database\Factories\ContatoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property string|null $cargo
 * @property int|null $empresa_id
 * @property int $tipo_pessoa_id
 * @property string|null $email
 * @property string|null $telefone
 * @property string|null $cpf
 * @property int $canal_contato_id
 * @property int $status_consentimento_id
 * @property bool $registro_mesclado
 * @property string|null $observacao_deduplicacao
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('contatos')]
#[Fillable([
    'nome',
    'cargo',
    'empresa_id',
    'tipo_pessoa_id',
    'email',
    'telefone',
    'cpf',
    'canal_contato_id',
    'status_consentimento_id',
    'registro_mesclado',
    'observacao_deduplicacao',
])]
class Contato extends Model
{
    /** @use HasFactory<ContatoFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'registro_mesclado' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'registro_mesclado' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<TipoPessoa, $this>
     */
    public function tipoPessoa(): BelongsTo
    {
        return $this->belongsTo(TipoPessoa::class);
    }

    /**
     * @return BelongsTo<CanalContato, $this>
     */
    public function canalContato(): BelongsTo
    {
        return $this->belongsTo(CanalContato::class);
    }

    /**
     * @return BelongsTo<StatusConsentimento, $this>
     */
    public function statusConsentimento(): BelongsTo
    {
        return $this->belongsTo(StatusConsentimento::class);
    }

    /**
     * @return HasMany<ConsentimentoContato, $this>
     */
    public function consentimentos(): HasMany
    {
        return $this->hasMany(ConsentimentoContato::class);
    }

    /**
     * @return HasMany<Negociacao, $this>
     */
    public function negociacoes(): HasMany
    {
        return $this->hasMany(Negociacao::class);
    }
}
