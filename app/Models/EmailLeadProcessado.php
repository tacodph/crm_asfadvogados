<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EmailLeadProcessadoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Log append-only de idempotência da captura de leads por e-mail: uma linha
 * por `Message-ID` visto. É a fonte de verdade que impede reprocessar o
 * mesmo e-mail duas vezes, independente do estado da flag `\Seen` no IMAP.
 *
 * Sem `BelongsToTenant` de propósito: é criado pelo comando de captura antes
 * do tenant estar "corrente" no processo, então guarda `tenant_id` explícito.
 *
 * @property int $id
 * @property string $message_id
 * @property int|null $tenant_id
 * @property int|null $email_lead_conta_id
 * @property int|null $contato_id
 * @property int|null $negociacao_id
 * @property string $status pendente|processado|erro
 * @property string|null $erro
 * @property string|null $payload_html
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('email_leads_processados')]
#[Fillable([
    'message_id',
    'tenant_id',
    'email_lead_conta_id',
    'contato_id',
    'negociacao_id',
    'status',
    'erro',
    'payload_html',
])]
class EmailLeadProcessado extends Model
{
    /** @use HasFactory<EmailLeadProcessadoFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<EmailLeadConta, $this>
     */
    public function emailLeadConta(): BelongsTo
    {
        return $this->belongsTo(EmailLeadConta::class);
    }

    /**
     * @return BelongsTo<Contato, $this>
     */
    public function contato(): BelongsTo
    {
        return $this->belongsTo(Contato::class);
    }

    /**
     * @return BelongsTo<Negociacao, $this>
     */
    public function negociacao(): BelongsTo
    {
        return $this->belongsTo(Negociacao::class);
    }
}
