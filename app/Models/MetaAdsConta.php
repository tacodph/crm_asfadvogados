<?php

namespace App\Models;

use App\Casts\SegredoMeta;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MetaAdsContaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Conta de anúncio do Meta Ads (Marketing API), uma linha por conta e por
 * tenant. O `access_token` (usuário de sistema, escopo `ads_read`) é cifrado
 * em repouso pelo cast `App\Casts\SegredoMeta` — mesma chave dedicada da CAPI
 * (`META_CAPI_ENCRYPTION_KEY`) — e nunca é serializado (`#[Hidden]`).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $nome
 * @property string $ad_account_id dígitos, sem o prefixo `act_`
 * @property string|null $business_id
 * @property string|null $moeda
 * @property string|null $fuso_horario
 * @property string|null $access_token cifrado no banco; o accessor devolve o texto puro
 * @property string|null $token_ultimos4
 * @property array<int, string>|null $token_scopes
 * @property Carbon|null $token_verificado_em
 * @property bool|null $token_valido
 * @property string|null $conta_status
 * @property int|null $atualizado_por_user_id
 * @property bool $ativo
 * @property Carbon|null $estrutura_sincronizada_em
 * @property Carbon|null $insights_sincronizados_em
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('meta_ads_contas')]
#[Fillable([
    'nome',
    'ad_account_id',
    'business_id',
    'moeda',
    'fuso_horario',
    'access_token',
    'token_ultimos4',
    'token_scopes',
    'token_verificado_em',
    'token_valido',
    'conta_status',
    'atualizado_por_user_id',
    'ativo',
    'estrutura_sincronizada_em',
    'insights_sincronizados_em',
])]
#[Hidden(['access_token'])]
class MetaAdsConta extends Model
{
    /** @use HasFactory<MetaAdsContaFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (MetaAdsConta $conta): void {
            if ($conta->isDirty('access_token') && $conta->access_token !== null) {
                $conta->token_ultimos4 = substr($conta->access_token, -4);
                // Token trocado ⇒ verificação anterior não vale mais.
                $conta->token_verificado_em = null;
                $conta->token_valido = null;
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_token' => SegredoMeta::class,
            'token_scopes' => 'array',
            'token_valido' => 'boolean',
            'token_verificado_em' => 'datetime',
            'estrutura_sincronizada_em' => 'datetime',
            'insights_sincronizados_em' => 'datetime',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Representação segura do token para a UI — usa a coluna em claro,
     * nunca decifra o segredo.
     */
    public function mascararToken(): string
    {
        return $this->token_ultimos4 !== null && $this->token_ultimos4 !== ''
            ? '••••'.$this->token_ultimos4
            : '—';
    }

    /**
     * Id do nó da conta na Graph API (`act_<id>`).
     */
    public function nodeId(): string
    {
        return 'act_'.$this->ad_account_id;
    }

    /**
     * O token tem o escopo pedido? (`ads_read`, `ads_management`)
     */
    public function temEscopo(string $escopo): bool
    {
        return in_array($escopo, $this->token_scopes ?? [], true);
    }

    /**
     * @param  Builder<MetaAdsConta>  $query
     */
    public function scopeAtivas(Builder $query): void
    {
        $query->where('ativo', true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por_user_id');
    }

    /**
     * @return HasMany<MetaAdsCampanha, $this>
     */
    public function campanhas(): HasMany
    {
        return $this->hasMany(MetaAdsCampanha::class, 'meta_ads_conta_id');
    }

    /**
     * @return HasMany<MetaAdsInsightDiario, $this>
     */
    public function insightsDiarios(): HasMany
    {
        return $this->hasMany(MetaAdsInsightDiario::class, 'meta_ads_conta_id');
    }

    /**
     * @return HasMany<MetaAdsSyncExecucao, $this>
     */
    public function syncExecucoes(): HasMany
    {
        return $this->hasMany(MetaAdsSyncExecucao::class, 'meta_ads_conta_id');
    }
}
