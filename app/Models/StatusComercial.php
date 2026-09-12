<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\StatusComercialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Lifecycle / commercial status shared by contacts and companies
 * (e.g. Novo, Em análise, Qualificado, Cliente efetivado).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $slug
 * @property string $nome
 * @property string|null $descricao
 * @property string $cor_fundo
 * @property string $cor_texto
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('status_comerciais')]
#[Fillable(['slug', 'nome', 'descricao', 'cor_fundo', 'cor_texto', 'ordem'])]
class StatusComercial extends Model
{
    /** @use HasFactory<StatusComercialFactory> */
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
     * @return HasMany<Contato, $this>
     */
    public function contatos(): HasMany
    {
        return $this->hasMany(Contato::class);
    }

    /**
     * @return HasMany<Empresa, $this>
     */
    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }
}
