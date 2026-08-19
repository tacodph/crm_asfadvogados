<?php

namespace App\Models;

use Database\Factories\StatusConsentimentoFactory;
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
 * @property string $cor_fundo
 * @property string $cor_texto
 * @property bool $visivel_cadastro
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('status_consentimentos')]
#[Fillable(['slug', 'nome', 'cor_fundo', 'cor_texto', 'visivel_cadastro', 'ordem'])]
class StatusConsentimento extends Model
{
    /** @use HasFactory<StatusConsentimentoFactory> */
    use HasFactory;

    /**
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'visivel_cadastro' => 'boolean',
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
     * @return HasMany<ConsentimentoContato, $this>
     */
    public function consentimentos(): HasMany
    {
        return $this->hasMany(ConsentimentoContato::class);
    }
}
