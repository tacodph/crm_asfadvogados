<?php

namespace App\Models;

use Database\Factories\FunilFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $slug
 * @property string $nome
 * @property string $distribuicao
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('funis')]
#[Fillable(['slug', 'nome', 'distribuicao', 'ordem'])]
class Funil extends Model
{
    /** @use HasFactory<FunilFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ordem' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    /**
     * @return HasMany<EtapaFunil, $this>
     */
    public function etapas(): HasMany
    {
        return $this->hasMany(EtapaFunil::class)->orderBy('ordem');
    }

    /**
     * @return HasMany<Negociacao, $this>
     */
    public function negociacoes(): HasMany
    {
        return $this->hasMany(Negociacao::class);
    }
}
