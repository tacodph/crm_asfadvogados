<?php

namespace App\Http\Controllers;

use App\Actions\Crm\DistribuirNegociacaoResponsavel;
use App\Actions\Crm\FindContatoDuplicatas;
use App\Http\Requests\StoreLeadTrafegoRequest;
use App\Models\CanalContato;
use App\Models\ConsentimentoContato;
use App\Models\Contato;
use App\Models\FinalidadeConsentimento;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\StatusComercial;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use App\Models\Uf;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Entrada pública de leads pagos (site / landing). O tenant vem do middleware
 * do token. Cria/atualiza o contato, registra o consentimento do opt-in e abre
 * a negociação com os parâmetros do Pixel — o NegociacaoObserver dispara o
 * evento `Lead` da CAPI e a atribuição de anúncio.
 */
class CapturarLeadTrafegoController extends Controller
{
    public function __invoke(
        StoreLeadTrafegoRequest $request,
        FindContatoDuplicatas $duplicatas,
        DistribuirNegociacaoResponsavel $distribuir,
    ): JsonResponse {
        /** @var array<string, mixed> $data */
        $data = $request->validated();

        $negociacao = DB::transaction(function () use ($data, $request, $duplicatas, $distribuir): Negociacao {
            $contato = $this->resolverContato($data, $duplicatas);
            $this->registrarConsentimento($contato, (string) $data['finalidade']);

            return $this->abrirNegociacao($contato, $data, $request->ip(), $request->userAgent(), $distribuir);
        });

        return response()->json([
            'ok' => true,
            'negociacao_id' => $negociacao->id,
            'contato_id' => $negociacao->contato_id,
        ], 201);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolverContato(array $data, FindContatoDuplicatas $duplicatas): Contato
    {
        $email = $this->texto($data, 'email');
        $telefone = $this->texto($data, 'telefone');
        $cpf = $this->texto($data, 'cpf');

        $match = $duplicatas($email, $telefone, $cpf)->first();

        if ($match !== null) {
            $contato = Contato::query()->findOrFail($match['contato']['id']);

            $contato->fill(array_filter([
                'cidade' => $contato->cidade ?? $this->texto($data, 'cidade'),
                'cep' => $contato->cep ?? $this->texto($data, 'cep'),
            ], static fn ($v): bool => $v !== null));

            if ($contato->uf_id === null && $this->ufId($data) !== null) {
                $contato->uf_id = $this->ufId($data);
            }

            $contato->save();

            return $contato;
        }

        return Contato::query()->create([
            'nome' => (string) $data['nome'],
            'email' => $email,
            'telefone' => $telefone,
            'cpf' => $cpf,
            'cidade' => $this->texto($data, 'cidade'),
            'cep' => $this->texto($data, 'cep'),
            'uf_id' => $this->ufId($data),
            'tipo_pessoa_id' => TipoPessoa::query()->where('slug', 'pf')->value('id'),
            'canal_contato_id' => $this->canalId($this->texto($data, 'canal') ?? 'site'),
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function abrirNegociacao(
        Contato $contato,
        array $data,
        ?string $ip,
        ?string $userAgent,
        DistribuirNegociacaoResponsavel $distribuir,
    ): Negociacao {
        $funilSlug = $this->texto($data, 'funil_slug');

        $funil = ($funilSlug !== null
            ? Funil::query()->where('slug', $funilSlug)->first()
            : null) ?? Funil::query()->orderBy('ordem')->firstOrFail();

        $etapa = $funil->etapas()->orderBy('ordem')->firstOrFail();

        $origemUtm = ['fonte' => 'site'] + array_filter([
            'utm_source' => $this->texto($data, 'utm_source'),
            'utm_medium' => $this->texto($data, 'utm_medium'),
            'utm_campaign' => $this->texto($data, 'utm_campaign'),
            'utm_content' => $this->texto($data, 'utm_content'),
            'utm_term' => $this->texto($data, 'utm_term'),
            'fbclid' => $this->texto($data, 'fbclid'),
        ], static fn ($v): bool => $v !== null && $v !== '');

        return Negociacao::query()->create([
            'funil_id' => $funil->id,
            'etapa_funil_id' => $etapa->id,
            'contato_id' => $contato->id,
            'empresa_id' => null,
            'canal_contato_id' => $this->canalId($this->texto($data, 'canal') ?? 'site'),
            'responsavel_user_id' => $distribuir($funil)->id,
            'assunto' => $this->texto($data, 'assunto') ?? 'Lead do site',
            'valor' => isset($data['valor']) ? (float) $data['valor'] : 0,
            'etapa_desde' => now(),
            'meta_event_id' => $this->texto($data, 'meta_event_id'),
            'meta_fbp' => $this->texto($data, 'meta_fbp'),
            'meta_fbc' => $this->texto($data, 'meta_fbc'),
            'meta_event_source_url' => $this->texto($data, 'meta_event_source_url'),
            'meta_client_ip' => $this->texto($data, 'client_ip_address') ?? $ip,
            'meta_client_user_agent' => $this->texto($data, 'client_user_agent') ?? $userAgent,
            'meta_captado_em' => now(),
            'origem_utm' => $origemUtm,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function texto(array $data, string $chave): ?string
    {
        $valor = $data[$chave] ?? null;

        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ufId(array $data): ?int
    {
        $sigla = $this->texto($data, 'uf');

        if ($sigla === null) {
            return null;
        }

        $id = Uf::query()->where('sigla', mb_strtoupper($sigla))->value('id');

        return is_int($id) ? $id : null;
    }

    private function canalId(string $slug): int
    {
        $id = CanalContato::query()->where('slug', $slug)->value('id')
            ?? CanalContato::query()->orderBy('ordem')->orderBy('id')->value('id');

        if (! is_int($id)) {
            throw new \RuntimeException('Nenhum canal de contato configurado para o tenant.');
        }

        return $id;
    }

    private function statusConsentimentoConcedidoId(): int
    {
        $slugs = config('meta.capi.slugs_consentimento_valido');
        $slugs = is_array($slugs) && $slugs !== [] ? $slugs : ['opt-in-registrado', 'vigente'];

        $id = StatusConsentimento::query()->whereIn('slug', $slugs)->orderByRaw('slug asc')->value('id');

        if (! is_int($id)) {
            throw new \RuntimeException('Nenhum status de consentimento "concedido" no catálogo do tenant.');
        }

        return $id;
    }

    private function statusComercialId(): int
    {
        $id = StatusComercial::query()->where('slug', 'novo')->value('id')
            ?? StatusComercial::query()->orderBy('ordem')->orderBy('id')->value('id');

        if (! is_int($id)) {
            throw new \RuntimeException('Nenhum status comercial configurado para o tenant.');
        }

        return $id;
    }
}
