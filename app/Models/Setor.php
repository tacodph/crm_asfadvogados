<?php

namespace App\Models;

use Database\Factories\SetorFactory;
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
#[Table('setores')]
#[Fillable(['slug', 'nome', 'ordem'])]
class Setor extends Model
{
    /** @use HasFactory<SetorFactory> */
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
     * @return HasMany<Empresa, $this>
     */
    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }
}
