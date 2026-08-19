<?php

namespace App\Models;

use Database\Factories\ConsentimentoContatoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $contato_id
 * @property int $finalidade_consentimento_id
 * @property int $status_consentimento_id
 * @property Carbon|null $concedido_em
 * @property Carbon|null $revogado_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('consentimentos_contato')]
#[Fillable([
    'contato_id',
    'finalidade_consentimento_id',
    'status_consentimento_id',
    'concedido_em',
    'revogado_em',
])]
class ConsentimentoContato extends Model
{
    /** @use HasFactory<ConsentimentoContatoFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'concedido_em' => 'date',
            'revogado_em' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Contato, $this>
     */
    public function contato(): BelongsTo
    {
        return $this->belongsTo(Contato::class);
    }

    /**
     * @return BelongsTo<FinalidadeConsentimento, $this>
     */
    public function finalidade(): BelongsTo
    {
        return $this->belongsTo(FinalidadeConsentimento::class, 'finalidade_consentimento_id');
    }

    /**
     * @return BelongsTo<StatusConsentimento, $this>
     */
    public function statusConsentimento(): BelongsTo
    {
        return $this->belongsTo(StatusConsentimento::class);
    }
}
