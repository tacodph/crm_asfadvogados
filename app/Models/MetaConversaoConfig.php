<?php

namespace App\Models;

use App\Casts\SegredoMeta;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\MetaConversaoConfigFactory;
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
 * Credenciais e políticas de uma campanha na API de Conversões da Meta,
 * uma linha por campanha e por tenant. O `access_token` / `test_event_code`
 * são cifrados em repouso pelo cast `App\Casts\SegredoMeta` (chave dedicada,
 * não o `APP_KEY`) e nunca são serializados (`#[Hidden]`).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $nome_campanha
 * @property string $slug
 * @property string $pixel_id
 * @property string|null $access_token cifrado no banco; o accessor devolve o texto puro
 * @property string|null $test_event_code cifrado no banco
 * @property string|null $token_ultimos4
 * @property Carbon|null $token_verificado_em
 * @property bool|null $token_valido
 * @property string $api_version
 * @property string $action_source
 * @property string|null $origem_url
 * @property string|null $finalidade_consentimento_slug
 * @property bool $ativo
 * @property Carbon|null $ultimo_evento_em
 * @property string|null $ultimo_status
 * @property int|null $atualizado_por_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('meta_conversao_configs')]
#[Fillable([
    'nome_campanha',
    'slug',
    'pixel_id',
    'access_token',
    'test_event_code',
    'token_ultimos4',
    'token_verificado_em',
    'token_valido',
    'api_version',
    'action_source',
    'origem_url',
    'finalidade_consentimento_slug',
    'ativo',
    'ultimo_evento_em',
    'ultimo_status',
    'atualizado_por_user_id',
])]
#[Hidden(['access_token', 'test_event_code'])]
class MetaConversaoConfig extends Model
{
    /** @use HasFactory<MetaConversaoConfigFactory> */
    use BelongsToTenant, HasFactory;

    protected static function booted(): void
    {
        static::saving(function (MetaConversaoConfig $config): void {
            if ($config->isDirty('access_token') && $config->access_token !== null) {
                $config->token_ultimos4 = substr($config->access_token, -4);
                // Token trocado ⇒ verificação anterior não vale mais.
                $config->token_verificado_em = null;
                $config->token_valido = null;
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
            'test_event_code' => SegredoMeta::class,
            'ativo' => 'boolean',
            'token_valido' => 'boolean',
            'token_verificado_em' => 'datetime',
            'ultimo_evento_em' => 'datetime',
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
     * @param  Builder<MetaConversaoConfig>  $query
     */
    public function scopeAtivas(Builder $query): void
    {
        $query->where('ativo', true);
    }

    /**
     * @return HasMany<MetaConversaoEvento, $this>
     */
    public function eventos(): HasMany
    {
        return $this->hasMany(MetaConversaoEvento::class);
    }

    /**
     * @return HasMany<MetaConversaoEstatistica, $this>
     */
    public function estatisticas(): HasMany
    {
        return $this->hasMany(MetaConversaoEstatistica::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por_user_id');
    }
}
