<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\SegredoMeta;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\EmailLeadContaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Uma caixa de e-mail (IMAP) monitorada pela captura de leads, cadastrada
 * pela equipe de tráfego na tela `/trafego → E-mail`. A senha é cifrada em
 * repouso pelo cast `App\Casts\SegredoMeta` (mesma chave dedicada da CAPI) e
 * nunca é serializada (`#[Hidden]`). `funil_id` e `finalidade_consentimento_slug`
 * definem para onde e sob qual base legal os leads capturados entram.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $nome
 * @property string $host
 * @property int $port
 * @property string $encryption
 * @property string $username
 * @property string|null $password cifrada no banco; o accessor devolve o texto puro
 * @property string $pasta
 * @property int $funil_id
 * @property string $finalidade_consentimento_slug
 * @property bool $ativo
 * @property Carbon|null $ultima_captura_em
 * @property string|null $ultimo_status
 * @property string|null $ultimo_erro
 * @property int|null $atualizado_por_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('email_leads_contas')]
#[Fillable([
    'nome',
    'host',
    'port',
    'encryption',
    'username',
    'password',
    'pasta',
    'funil_id',
    'finalidade_consentimento_slug',
    'ativo',
    'ultima_captura_em',
    'ultimo_status',
    'ultimo_erro',
    'atualizado_por_user_id',
])]
#[Hidden(['password'])]
class EmailLeadConta extends Model
{
    /** @use HasFactory<EmailLeadContaFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'port' => 'integer',
            'password' => SegredoMeta::class,
            'ativo' => 'boolean',
            'ultima_captura_em' => 'datetime',
        ];
    }

    /**
     * Configuração pronta para `Webklex\PHPIMAP\ClientManager::make()`.
     *
     * @return array{host: string, port: int, encryption: string, validate_cert: bool, username: string, password: string, protocol: string}
     */
    public function credenciaisImap(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'encryption' => $this->encryption,
            'validate_cert' => true,
            'username' => $this->username,
            'password' => (string) $this->password,
            'protocol' => 'imap',
        ];
    }

    /**
     * @param  Builder<EmailLeadConta>  $query
     */
    public function scopeAtivas(Builder $query): void
    {
        $query->where('ativo', true);
    }

    /**
     * @return BelongsTo<Funil, $this>
     */
    public function funil(): BelongsTo
    {
        return $this->belongsTo(Funil::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'atualizado_por_user_id');
    }
}
