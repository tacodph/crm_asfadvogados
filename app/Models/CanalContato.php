<?php

namespace App\Models;

use Database\Factories\CanalContatoFactory;
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
 * @property string $cor
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('canais_contato')]
#[Fillable(['slug', 'nome', 'cor', 'ordem'])]
class CanalContato extends Model
{
    /** @use HasFactory<CanalContatoFactory> */
    use HasFactory;

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
