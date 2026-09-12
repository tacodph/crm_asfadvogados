<?php

namespace App\Models;

use App\Enums\StatusTarefaNegociacao;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\TarefaNegociacaoFactory;
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
 * @property string $descricao
 * @property Carbon $data
 * @property Carbon|null $hora
 * @property StatusTarefaNegociacao $status
 * @property Carbon|null $concluida_em
 * @property int|null $criado_por_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('tarefas_negociacao')]
#[Fillable([
    'negociacao_id',
    'descricao',
    'data',
    'hora',
    'status',
    'concluida_em',
    'criado_por_user_id',
])]
class TarefaNegociacao extends Model
{
    /** @use HasFactory<TarefaNegociacaoFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => StatusTarefaNegociacao::Pendente->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'hora' => 'datetime:H:i',
            'status' => StatusTarefaNegociacao::class,
            'concluida_em' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Negociacao, $this>
     */
    public function negociacao(): BelongsTo
    {
        return $this->belongsTo(Negociacao::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }
}
