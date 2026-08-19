<?php

namespace App\Models;

use Database\Factories\EmpresaFactory;
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
 * @property string $cnpj
 * @property int $setor_id
 * @property string $porte
 * @property string $cidade
 * @property int $uf_id
 * @property int|null $responsavel_user_id
 * @property int $status_conflito_id
 * @property string|null $conflito_texto
 * @property Carbon|null $conflito_verificado_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('empresas')]
#[Fillable([
    'nome',
    'cnpj',
    'setor_id',
    'porte',
    'cidade',
    'uf_id',
    'responsavel_user_id',
    'status_conflito_id',
    'conflito_texto',
    'conflito_verificado_em',
])]
class Empresa extends Model
{
    /** @use HasFactory<EmpresaFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conflito_verificado_em' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Setor, $this>
     */
    public function setor(): BelongsTo
    {
        return $this->belongsTo(Setor::class);
    }

    /**
     * @return BelongsTo<Uf, $this>
     */
    public function uf(): BelongsTo
    {
        return $this->belongsTo(Uf::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    /**
     * @return BelongsTo<StatusConflito, $this>
     */
    public function statusConflito(): BelongsTo
    {
        return $this->belongsTo(StatusConflito::class);
    }

    /**
     * @return HasMany<Contato, $this>
     */
    public function contatos(): HasMany
    {
        return $this->hasMany(Contato::class);
    }

    /**
     * @return HasMany<Negociacao, $this>
     */
    public function negociacoes(): HasMany
    {
        return $this->hasMany(Negociacao::class);
    }
}
