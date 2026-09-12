<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\HistoricoNegociacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $negociacao_id
 * @property string $tipo
 * @property string $titulo
 * @property string $descricao
 * @property string $autor
 * @property Carbon $ocorrido_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('historicos_negociacao')]
#[Fillable([
    'negociacao_id',
    'tipo',
    'titulo',
    'descricao',
    'autor',
    'ocorrido_em',
])]
class HistoricoNegociacao extends Model
{
    /** @use HasFactory<HistoricoNegociacaoFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ocorrido_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Negociacao, $this>
     */
    public function negociacao(): BelongsTo
    {
        return $this->belongsTo(Negociacao::class);
    }
}
