<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FinalidadeConsentimentoFactory;
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
 * @property int $ordem
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('finalidades_consentimento')]
#[Fillable(['slug', 'nome', 'ordem'])]
class FinalidadeConsentimento extends Model
{
    /** @use HasFactory<FinalidadeConsentimentoFactory> */
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
     * @return HasMany<ConsentimentoContato, $this>
     */
    public function consentimentos(): HasMany
    {
        return $this->hasMany(ConsentimentoContato::class, 'finalidade_consentimento_id');
    }
}
