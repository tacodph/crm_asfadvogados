<?php

declare(strict_types=1);

namespace App\Actions\LeadsEmail;

use App\Actions\Crm\DistribuirNegociacaoResponsavel;
use App\Actions\Crm\FindContatoDuplicatas;
use App\Models\CanalContato;
use App\Models\ConsentimentoContato;
use App\Models\Contato;
use App\Models\EmailLeadConta;
use App\Models\FinalidadeConsentimento;
use App\Models\Negociacao;
use App\Models\StatusComercial;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use App\Support\LeadsEmail\LeadEmailDados;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Persiste um lead capturado por e-mail no mesmo modelo de dados do endpoint
 * público de tráfego (`CapturarLeadTrafegoController`): contato dedup-or-create
 * → consentimento LGPD → negociação. O funil e a finalidade de consentimento
 * vêm da `EmailLeadConta` que capturou o e-mail (tela `/trafego → E-mail`),
 * não de um valor fixo no código.
 */
final class RegistrarLeadEmail
{
    private const CANAL_SLUG = 'email';

    public function __construct(
        private readonly FindContatoDuplicatas $duplicatas,
        private readonly DistribuirNegociacaoResponsavel $distribuir,
    ) {}

    public function __invoke(LeadEmailDados $dados, EmailLeadConta $conta): RegistrarLeadEmailResultado
    {
        return DB::transaction(function () use ($dados, $conta): RegistrarLeadEmailResultado {
            $contato = $this->resolverContato($dados);
            $this->registrarConsentimento($contato, $conta->finalidade_consentimento_slug);
            $negociacao = $this->abrirNegociacao($contato, $dados, $conta);

            return new RegistrarLeadEmailResultado($contato, $negociacao);
        });
    }

    private function resolverContato(LeadEmailDados $dados): Contato
    {
        $match = ($this->duplicatas)($dados->email, $dados->telefone)->first();

        if ($match !== null) {
            return Contato::query()->findOrFail($match['contato']['id']);
        }

        return Contato::query()->create([
            'nome' => $dados->nome,
            'email' => $dados->email,
            'telefone' => $dados->telefone,
            // Sem CNPJ/CPF no e-mail: não dá pra abrir/casar uma Empresa com
            // segurança, então PJ fica só marcado no tipo_pessoa, sem empresa_id.
            'tipo_pessoa_id' => TipoPessoa::query()->where('slug', $dados->tipoPessoa)->value('id'),
            'canal_contato_id' => $this->canalId(self::CANAL_SLUG),
            'status_consentimento_id' => $this->statusConsentimentoConcedidoId(),
            'status_comercial_id' => $this->statusComercialId(),
        ]);
    }

    private function registrarConsentimento(Contato $contato, string $finalidadeSlug): void
    {
        $finalidade = FinalidadeConsentimento::query()->where('slug', $finalidadeSlug)->firstOrFail();

        ConsentimentoContato::query()->updateOrCreate(
            ['contato_id' => $contato->id, 'finalidade_consentimento_id' => $finalidade->id],
            [
                'status_consentimento_id' => $this->statusConsentimentoConcedidoId(),
                'concedido_em' => now()->toDateString(),
                'revogado_em' => null,
            ],
        );
    }

    private function abrirNegociacao(Contato $contato, LeadEmailDados $dados, EmailLeadConta $conta): Negociacao
    {
        $funil = $conta->funil()->firstOrFail();
        $etapa = $funil->etapas()->orderBy('ordem')->firstOrFail();

        // `utm_campaign` recebe o nome da campanha porque AtribuirNegociacaoAnuncioMeta
        // tenta casar contra MetaAdsCampanha.nome (case-insensitive) — se baterem, a
        // atribuição automática de anúncio ainda funciona; se não, fica só metadado.
        $origemUtm = [
            'fonte' => 'email',
            'utm_campaign' => $dados->campanha,
            'campanha_nome' => $dados->campanha,
            'conjunto_nome' => $dados->conjunto,
            'anuncio_nome' => $dados->anuncio,
        ];

        return Negociacao::query()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'contato_id' => $contato->id,
            'empresa_id' => null,
            'canal_contato_id' => $this->canalId(self::CANAL_SLUG),
            'responsavel_user_id' => ($this->distribuir)($funil)->id,
            'assunto' => 'Lead recebido por e-mail',
            'valor' => $dados->valorDivida,
            'etapa_desde' => now(),
            'origem_utm' => $origemUtm,
        ]);
    }

    private function canalId(string $slug): int
    {
        $id = CanalContato::query()->where('slug', $slug)->value('id')
            ?? CanalContato::query()->orderBy('ordem')->orderBy('id')->value('id');

        if (! is_int($id)) {
            throw new RuntimeException('Nenhum canal de contato configurado para o tenant.');
        }

        return $id;
    }

    private function statusConsentimentoConcedidoId(): int
    {
        $slugs = config('meta.capi.slugs_consentimento_valido');
        $slugs = is_array($slugs) && $slugs !== [] ? $slugs : ['opt-in-registrado', 'vigente'];

        $id = StatusConsentimento::query()->whereIn('slug', $slugs)->orderByRaw('slug asc')->value('id');

        if (! is_int($id)) {
            throw new RuntimeException('Nenhum status de consentimento "concedido" no catálogo do tenant.');
        }

        return $id;
    }

    private function statusComercialId(): int
    {
        $id = StatusComercial::query()->where('slug', 'novo')->value('id')
            ?? StatusComercial::query()->orderBy('ordem')->orderBy('id')->value('id');

        if (! is_int($id)) {
            throw new RuntimeException('Nenhum status comercial configurado para o tenant.');
        }

        return $id;
    }
}
