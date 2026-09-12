<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Observers\NegociacaoObserver;
use Database\Factories\NegociacaoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $funil_id
 * @property int $etapa_funil_id
 * @property int|null $empresa_id
 * @property int $contato_id
 * @property int $canal_contato_id
 * @property int|null $status_atendimento_id
 * @property int|null $status_qualificacao_id
 * @property string|null $motivo_desqualificacao
 * @property string|null $continuidade_atendimento
 * @property string|null $observacoes_complementares
 * @property int $responsavel_user_id
 * @property string $assunto
 * @property string $valor
 * @property Carbon|null $previsao_fechamento
 * @property Carbon $etapa_desde
 * @property Carbon|null $concluida_em
 * @property string|null $proxima_tarefa
 * @property Carbon|null $proxima_tarefa_em
 * @property Carbon|null $proxima_tarefa_hora
 * @property string|null $meta_event_id
 * @property string|null $meta_fbp
 * @property string|null $meta_fbc
 * @property string|null $meta_event_source_url
 * @property string|null $meta_client_ip
 * @property string|null $meta_client_user_agent
 * @property Carbon|null $meta_captado_em
 * @property string|null $meta_ad_id
 * @property string|null $meta_adset_id
 * @property string|null $meta_campaign_id
 * @property string|null $meta_atribuicao_origem
 * @property Carbon|null $meta_atribuido_em
 * @property array<string, mixed>|null $origem_utm
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('negociacoes')]
#[ObservedBy([NegociacaoObserver::class])]
#[Fillable([
    'funil_id',
    'etapa_funil_id',
    'empresa_id',
    'contato_id',
    'canal_contato_id',
    'status_atendimento_id',
    'status_qualificacao_id',
    'motivo_desqualificacao',
    'continuidade_atendimento',
    'observacoes_complementares',
    'responsavel_user_id',
    'assunto',
    'valor',
    'previsao_fechamento',
    'etapa_desde',
    'concluida_em',
    'proxima_tarefa',
    'proxima_tarefa_em',
    'proxima_tarefa_hora',
    // Parâmetros do Pixel do navegador. Os `meta_client_*` / `meta_captado_em`
    // só são preenchidos pelo controller (o request de criação não os valida),
    // por isso estar no Fillable aqui não abre brecha de mass-assignment.
    'meta_event_id',
    'meta_fbp',
    'meta_fbc',
    'meta_event_source_url',
    'meta_client_ip',
    'meta_client_user_agent',
    'meta_captado_em',
    // JSON com utm_*/fbclid/meta_ad_id crus da entrada (o controller monta;
    // o request valida cada campo). A resolução vai nas colunas meta_*_id,
    // gravadas só pela Action AtribuirNegociacaoAnuncioMeta (fora do Fillable).
    'origem_utm',
])]
class Negociacao extends Model
{
    /** @use HasFactory<NegociacaoFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'previsao_fechamento' => 'date',
            'etapa_desde' => 'datetime',
            'concluida_em' => 'datetime',
            'proxima_tarefa_em' => 'date',
            'proxima_tarefa_hora' => 'datetime:H:i',
            'meta_captado_em' => 'datetime',
            'meta_atribuido_em' => 'datetime',
            'origem_utm' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Funil, $this>
     */
    public function funil(): BelongsTo
    {
        return $this->belongsTo(Funil::class);
    }

    /**
     * @return BelongsTo<EtapaFunil, $this>
     */
    public function etapaFunil(): BelongsTo
    {
        return $this->belongsTo(EtapaFunil::class);
    }

    /**
     * @return BelongsTo<Empresa, $this>
     */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @return BelongsTo<Contato, $this>
     */
    public function contato(): BelongsTo
    {
        return $this->belongsTo(Contato::class);
    }

    /**
     * @return BelongsTo<CanalContato, $this>
     */
    public function canalContato(): BelongsTo
    {
        return $this->belongsTo(CanalContato::class);
    }

    /**
     * @return BelongsTo<StatusAtendimento, $this>
     */
    public function statusAtendimento(): BelongsTo
    {
        return $this->belongsTo(StatusAtendimento::class);
    }

    /**
     * @return BelongsTo<StatusQualificacao, $this>
     */
    public function statusQualificacao(): BelongsTo
    {
        return $this->belongsTo(StatusQualificacao::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    /**
     * @return HasMany<HistoricoNegociacao, $this>
     */
    public function historicos(): HasMany
    {
        return $this->hasMany(HistoricoNegociacao::class)
            ->orderByDesc('ocorrido_em')
            ->orderByDesc('id');
    }

    /**
     * @return HasMany<TarefaNegociacao, $this>
     */
    public function tarefas(): HasMany
    {
        return $this->hasMany(TarefaNegociacao::class)
            ->orderBy('data')
            ->orderByRaw('hora is null')
            ->orderBy('hora');
    }

    /**
     * @return HasMany<Proposta, $this>
     */
    public function propostas(): HasMany
    {
        return $this->hasMany(Proposta::class);
    }
}
