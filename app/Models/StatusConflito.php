<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StatusConflitoFactory;
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
 * @property string $cor_fundo_detalhe
 * @property string $cor_borda_detalhe
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('status_conflitos')]
#[Fillable(['slug', 'nome', 'cor_fundo', 'cor_texto', 'cor_fundo_detalhe', 'cor_borda_detalhe', 'ordem'])]
class StatusConflito extends Model
{
    /** @use HasFactory<StatusConflitoFactory> */
    use BelongsToTenant, HasFactory;

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
