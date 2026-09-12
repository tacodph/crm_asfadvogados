<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\FunilFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $slug
 * @property string $nome
 * @property string $distribuicao
 * @property int $ordem
 * @property int|null $ultimo_responsavel_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('funis')]
#[Fillable(['slug', 'nome', 'distribuicao', 'ordem', 'ultimo_responsavel_user_id'])]
class Funil extends Model
{
    /** @use HasFactory<FunilFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Regras de distribuição cicladas pelo botão “Alterar regra” no funil.
     *
     * @var list<string>
     */
    public const REGRAS_DISTRIBUICAO = [
        self::DISTRIBUICAO_ESPECIALIDADE,
        self::DISTRIBUICAO_SIMPLES,
        self::DISTRIBUICAO_CARGA,
    ];

    public const DISTRIBUICAO_ESPECIALIDADE = 'round robin por especialidade';

    public const DISTRIBUICAO_SIMPLES = 'round robin simples';

    public const DISTRIBUICAO_CARGA = 'por carga de trabalho';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'ordem' => 1,
    ];

    /**
     * Next distribution rule in the design cycle.
     */
    public function proximaRegraDistribuicao(): string
    {
        $regras = self::REGRAS_DISTRIBUICAO;
        $indice = array_search($this->distribuicao, $regras, true);
        $proximo = (($indice === false ? -1 : $indice) + 1) % count($regras);

        return $regras[$proximo];
    }

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
     * @return BelongsTo<User, $this>
     */
    public function ultimoResponsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ultimo_responsavel_user_id');
    }

    /**
     * @return HasMany<EtapaFunil, $this>
     */
    public function etapas(): HasMany
    {
        return $this->hasMany(EtapaFunil::class)->orderBy('ordem');
    }

    /**
     * @return HasMany<Negociacao, $this>
     */
    public function negociacoes(): HasMany
    {
        return $this->hasMany(Negociacao::class);
    }
}
