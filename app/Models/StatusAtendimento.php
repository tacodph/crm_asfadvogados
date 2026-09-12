<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StatusAtendimentoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $slug
 * @property string $nome
 * @property string $cor_fundo
 * @property string $cor_texto
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('status_atendimentos')]
#[Fillable(['slug', 'nome', 'cor_fundo', 'cor_texto', 'ordem'])]
class StatusAtendimento extends Model
{
    /** @use HasFactory<StatusAtendimentoFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
        ];
    }

    /**
     * @return HasMany<Negociacao, $this>
     */
    public function negociacoes(): HasMany
    {
        return $this->hasMany(Negociacao::class);
    }
}
