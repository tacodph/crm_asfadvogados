<?php

namespace App\Models;

use Database\Factories\TipoPessoaFactory;
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
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('tipos_pessoa')]
#[Fillable(['slug', 'nome', 'ordem'])]
class TipoPessoa extends Model
{
    /** @use HasFactory<TipoPessoaFactory> */
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
}
