<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
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
 * @property int $tenant_id
 * @property string $nome
 * @property string|null $cargo
 * @property int|null $empresa_id
 * @property int $tipo_pessoa_id
 * @property string|null $email
 * @property string|null $telefone
 * @property string|null $cpf
 * @property string|null $cidade
 * @property string|null $cep
 * @property int|null $uf_id
 * @property int|null $municipio_id
 * @property int $canal_contato_id
 * @property int $status_consentimento_id
 * @property int $status_comercial_id
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
    'cidade',
    'cep',
    'uf_id',
    'municipio_id',
    'canal_contato_id',
    'status_consentimento_id',
    'status_comercial_id',
    'registro_mesclado',
    'observacao_deduplicacao',
])]
class Contato extends Model
{
    /** @use HasFactory<ContatoFactory> */
    use BelongsToTenant, HasFactory;

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
     * @return BelongsTo<Uf, $this>
     */
    public function uf(): BelongsTo
    {
        return $this->belongsTo(Uf::class);
    }

    /**
     * @return BelongsTo<IbgeMunicipio, $this>
     */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(IbgeMunicipio::class, 'municipio_id');
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
     * @return BelongsTo<StatusComercial, $this>
     */
    public function statusComercial(): BelongsTo
    {
        return $this->belongsTo(StatusComercial::class);
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

    /**
     * @return HasMany<Proposta, $this>
     */
    public function propostas(): HasMany
    {
        return $this->hasMany(Proposta::class);
    }
}
