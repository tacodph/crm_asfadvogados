<?php

namespace Database\Seeders;

use App\Models\CanalContato;
use App\Models\ConsentimentoContato;
use App\Models\Contato;
use App\Models\Empresa;
use App\Models\EtapaFunil;
use App\Models\FinalidadeConsentimento;
use App\Models\Funil;
use App\Models\HistoricoNegociacao;
use App\Models\IbgeMunicipio;
use App\Models\Negociacao;
use App\Models\Role;
use App\Models\Setor;
use App\Models\StatusComercial;
use App\Models\StatusConflito;
use App\Models\StatusConsentimento;
use App\Models\TipoPessoa;
use App\Models\Uf;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\Concerns\SeedsForDevTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Cria o funil "Parte empresarial PJ Meta Ads" e importa os leads do
 * formulário Meta Ads (aba REV-PJ) para empresas, contatos e negociações.
 *
 * Fonte: database/data/meta-ads-pj-rev.json
 * (exportado de documents/Formulário Meta Ads - Adauto & Souza Advogados.xlsx)
 */
class MetaAdsPjSeeder extends Seeder
{
    use SeedsForDevTenant;

    /**
     * Optional override used by tests (absolute or relative path to JSON).
     */
    public string $dataPath = '';

    /**
     * @var array<string, EtapaFunil>
     */
    private array $etapasPorChave = [];

    private Funil $funil;

    private CanalContato $canalMetaAds;

    private TipoPessoa $tipoPj;

    private StatusConsentimento $consentOptIn;

    private FinalidadeConsentimento $finalidadeContato;

    private FinalidadeConsentimento $finalidadeMensagens;

    private StatusComercial $statusComercial;

    private StatusConflito $statusConflito;

    private Setor $setor;

    private User $responsavel;

    private Uf $ufPadrao;

    private ?int $municipioPadraoId = null;

    public function run(): void
    {
        $this->call(DominioCrmSeeder::class);

        app(CurrentTenant::class)->runAs($this->devTenant(), function (): void {
            $this->ensureFunil();
            $this->carregarCatalogos();
            $this->ensureResponsavel();

            foreach ($this->linhas() as $index => $linha) {
                $this->importarLinha($linha, $index + 1);
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function linhas(): array
    {
        $path = $this->dataPath !== ''
            ? $this->dataPath
            : database_path('data/meta-ads-pj-rev.json');

        if (! File::isFile($path)) {
            throw new RuntimeException("Arquivo de importação não encontrado: {$path}");
        }

        /** @var list<array<string, mixed>> $linhas */
        $linhas = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return $linhas;
    }

    private function ensureFunil(): void
    {
        $this->funil = Funil::query()->updateOrCreate(
            ['slug' => 'pj-meta-ads'],
            [
                'nome' => 'Parte empresarial PJ Meta Ads',
                'distribuicao' => 'round robin simples',
                'ordem' => 3,
            ],
        );

        $definicoes = [
            ['chave' => 'novo_lead', 'nome' => 'Novo lead', 'sla' => '15min', 'campos' => ['telefone válido', 'consentimento', 'valor da dívida'], 'exige' => false, 'resultado' => 'aberta', 'cor' => '#333A45'],
            ['chave' => 'follow_up_1', 'nome' => 'Follow-up 1', 'sla' => '1d', 'campos' => ['tentativa de contato D+1'], 'exige' => false, 'resultado' => 'aberta', 'cor' => '#31496E'],
            ['chave' => 'follow_up_2', 'nome' => 'Follow-up 2', 'sla' => '1d', 'campos' => ['tentativa de contato D+2'], 'exige' => false, 'resultado' => 'aberta', 'cor' => '#41503A'],
            ['chave' => 'follow_up_3', 'nome' => 'Follow-up 3', 'sla' => '1d', 'campos' => ['tentativa de contato D+3'], 'exige' => false, 'resultado' => 'aberta', 'cor' => '#6B5330'],
            ['chave' => 'follow_up_4', 'nome' => 'Follow-up 4', 'sla' => '1d', 'campos' => ['tentativa de contato D+4'], 'exige' => false, 'resultado' => 'aberta', 'cor' => '#6B3852'],
            ['chave' => 'follow_up_5', 'nome' => 'Follow-up 5', 'sla' => '1d', 'campos' => ['tentativa de contato D+5', 'desqualificação'], 'exige' => true, 'resultado' => 'aberta', 'cor' => '#414A57'],
            ['chave' => 'qualificado', 'nome' => 'Qualificado', 'sla' => '2d', 'campos' => ['CAPAG', 'porte do passivo', 'decisor'], 'exige' => true, 'resultado' => 'aberta', 'cor' => '#14574F'],
            ['chave' => 'reuniao_agendada', 'nome' => 'Reunião agendada', 'sla' => '3d', 'campos' => ['data da reunião', 'responsável'], 'exige' => true, 'resultado' => 'aberta', 'cor' => '#3F5E8C'],
            ['chave' => 'proposta_apresentada', 'nome' => 'Proposta apresentada', 'sla' => '5d', 'campos' => ['proposta enviada', 'honorários'], 'exige' => true, 'resultado' => 'aberta', 'cor' => '#8C6F3F'],
            ['chave' => 'no_show', 'nome' => 'No show', 'sla' => '—', 'campos' => ['motivo do não comparecimento'], 'exige' => true, 'resultado' => 'perdido', 'cor' => '#9B3B2F'],
            ['chave' => 'contrato_fechado', 'nome' => 'Contrato fechado', 'sla' => '—', 'campos' => ['contrato assinado', 'procuração'], 'exige' => false, 'resultado' => 'ganho', 'cor' => '#0F4A43'],
        ];

        foreach ($definicoes as $i => $etapa) {
            $resultado = $etapa['resultado'];
            $ganho = $resultado === 'ganho';
            $perdido = $resultado === 'perdido';

            $model = EtapaFunil::query()->updateOrCreate(
                [
                    'funil_id' => $this->funil->id,
                    'ordem' => $i + 1,
                ],
                [
                    'nome' => $etapa['nome'],
                    'sla' => $etapa['sla'],
                    'campos' => $etapa['campos'],
                    'exige_motivo' => $etapa['exige'],
                    'resultado' => $resultado,
                    'cor_fundo' => $etapa['cor'],
                    'cor_texto' => '#FBF9F4',
                    'cor_suave' => $perdido || $ganho
                        ? 'rgba(251,249,244,0.92)'
                        : 'rgba(251,249,244,0.9)',
                ],
            );

            $this->etapasPorChave[$etapa['chave']] = $model;
        }
    }

    private function carregarCatalogos(): void
    {
        $this->canalMetaAds = CanalContato::query()->where('slug', 'meta-ads')->firstOrFail();
        $this->tipoPj = TipoPessoa::query()->where('slug', 'pj')->firstOrFail();
        $this->consentOptIn = StatusConsentimento::query()->where('slug', 'opt-in-registrado')->firstOrFail();
        $this->finalidadeContato = FinalidadeConsentimento::query()->where('slug', 'contato-comercial')->firstOrFail();
        $this->finalidadeMensagens = FinalidadeConsentimento::query()->where('slug', 'mensagens')->firstOrFail();
        $this->statusComercial = StatusComercial::query()->where('slug', 'em-analise')->firstOrFail();
        $this->statusConflito = StatusConflito::query()->where('slug', 'pendente')->firstOrFail();
        $this->setor = Setor::query()->where('slug', 'software-b2b')->firstOrFail();
        $this->ufPadrao = Uf::query()->where('sigla', 'DF')->firstOrFail();

        $municipio = IbgeMunicipio::query()->where('txt_nome_municipios', 'Brasília')->first();
        $this->municipioPadraoId = $municipio?->id;
    }

    private function ensureResponsavel(): void
    {
        $this->responsavel = User::query()->updateOrCreate(
            ['email' => 'comercial.pj@asfadvogados.adv.br'],
            [
                'name' => 'Comercial PJ Meta Ads',
                'password' => 'password',
                'role' => Role::VENDEDOR,
                'especialidades' => ['gestao-passivo-pj', 'rev-pj'],
                'ausente_ate' => null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    private function importarLinha(array $linha, int $ordem): void
    {
        $dataEntrada = $this->data($linha['data_entrada'] ?? null) ?? Carbon::parse('2026-05-07');
        $nome = trim((string) ($linha['nome'] ?? '')) ?: 'Lead sem nome';
        $telefone = $this->telefone($linha['telefone'] ?? null);
        $email = $this->email($linha['email'] ?? null);
        $valorDivida = trim((string) ($linha['valor_divida'] ?? ''));
        $campanha = trim((string) ($linha['campanha'] ?? '')) ?: 'REV-PJ Meta Ads';
        $anuncio = trim((string) ($linha['anuncio'] ?? ''));
        $publico = trim((string) ($linha['publico'] ?? ''));
        $nota = $this->textoOpcional($linha['nota_operacional'] ?? null);
        $zona = $this->textoOpcional($linha['zona_status'] ?? null);
        $etapaChave = (string) ($linha['etapa_sugerida'] ?? 'novo_lead');
        $etapa = $this->etapasPorChave[$etapaChave] ?? $this->etapasPorChave['novo_lead'];

        $localidade = $this->localidadePorTelefone($telefone);
        $valor = $this->valorNumerico($valorDivida);
        $porte = $this->porte($valorDivida);
        $assunto = $this->assunto($campanha, $anuncio, $valorDivida);

        $empresa = Empresa::query()->create([
            'nome' => $this->nomeEmpresa($nome, $nota),
            'cnpj' => null,
            'setor_id' => $this->setor->id,
            'porte' => $porte,
            'cidade' => $localidade['cidade'],
            'uf_id' => $localidade['uf_id'],
            'municipio_id' => $localidade['municipio_id'],
            'responsavel_user_id' => $this->responsavel->id,
            'status_conflito_id' => $this->statusConflito->id,
            'status_comercial_id' => $this->statusComercial->id,
            'conflito_texto' => 'Lead Meta Ads REV-PJ — CNPJ não informado no formulário.',
            'conflito_verificado_em' => null,
        ]);

        $contato = Contato::query()->create([
            'nome' => $nome,
            'cargo' => 'Decisor / Lead PJ — Meta Ads',
            'empresa_id' => $empresa->id,
            'tipo_pessoa_id' => $this->tipoPj->id,
            'email' => $email,
            'telefone' => $telefone,
            'cpf' => null,
            'cidade' => $localidade['cidade'],
            'uf_id' => $localidade['uf_id'],
            'municipio_id' => $localidade['municipio_id'],
            'canal_contato_id' => $this->canalMetaAds->id,
            'status_consentimento_id' => $this->consentOptIn->id,
            'status_comercial_id' => $this->statusComercial->id,
            'registro_mesclado' => false,
            'observacao_deduplicacao' => 'Importação Meta Ads PJ · linha '.$ordem,
        ]);

        $this->criarConsentimentos($contato, $dataEntrada);

        $observacoes = trim(implode("\n", array_filter([
            $valorDivida !== '' ? 'Valor da dívida (form): '.$valorDivida : null,
            $zona !== null ? 'Status operacional (Zona de Guerra): '.$zona : null,
            $nota !== null ? 'Nota operacional: '.$nota : null,
            $publico !== '' ? 'Público Meta: '.$publico : null,
            $anuncio !== '' ? 'Anúncio Meta: '.$anuncio : null,
            ! empty($linha['em_aba_reuniao']) ? 'Presente na aba REV-PJ REUNIÃO.' : null,
            ! empty($linha['capag']) ? 'CAPAG: '.(string) $linha['capag'] : null,
        ])));

        $proxima = $this->proximaTarefa($etapaChave, $dataEntrada);

        $negociacao = Negociacao::withoutEvents(function () use (
            $etapa,
            $empresa,
            $contato,
            $assunto,
            $valor,
            $dataEntrada,
            $observacoes,
            $proxima,
            $campanha,
            $anuncio,
            $publico,
        ): Negociacao {
            $negociacao = new Negociacao([
                'funil_id' => $this->funil->id,
                'etapa_funil_id' => $etapa->id,
                'empresa_id' => $empresa->id,
                'contato_id' => $contato->id,
                'canal_contato_id' => $this->canalMetaAds->id,
                'responsavel_user_id' => $this->responsavel->id,
                'assunto' => $assunto,
                'valor' => $valor,
                'previsao_fechamento' => $dataEntrada->copy()->addDays(30)->toDateString(),
                'etapa_desde' => $dataEntrada,
                'concluida_em' => $etapa->isGanho() ? $dataEntrada->copy()->addDays(7) : null,
                'proxima_tarefa' => $proxima['tarefa'],
                'proxima_tarefa_em' => $proxima['em'],
                'proxima_tarefa_hora' => $proxima['hora'],
                'observacoes_complementares' => $observacoes !== '' ? $observacoes : null,
                'origem_utm' => [
                    'utm_source' => 'meta',
                    'utm_medium' => 'cpc',
                    'utm_campaign' => $campanha,
                    'utm_content' => $anuncio !== '' ? $anuncio : null,
                    'utm_term' => $publico !== '' ? $publico : null,
                ],
                'meta_captado_em' => $dataEntrada,
            ]);

            // withoutEvents desliga o boot BelongsToTenant; preenche manualmente.
            $negociacao->tenant_id = app(CurrentTenant::class)->id();
            $negociacao->created_at = $dataEntrada;
            $negociacao->updated_at = $dataEntrada;
            $negociacao->save();

            return $negociacao;
        });

        $this->registrarHistorico($negociacao, $dataEntrada, $campanha, $anuncio, $etapa->nome, $zona, $nota);
    }

    private function criarConsentimentos(Contato $contato, Carbon $quando): void
    {
        foreach ([$this->finalidadeContato, $this->finalidadeMensagens] as $finalidade) {
            ConsentimentoContato::query()->create([
                'contato_id' => $contato->id,
                'finalidade_consentimento_id' => $finalidade->id,
                'status_consentimento_id' => $this->consentOptIn->id,
                'concedido_em' => $quando,
                'revogado_em' => null,
            ]);
        }
    }

    private function registrarHistorico(
        Negociacao $negociacao,
        Carbon $dataEntrada,
        string $campanha,
        string $anuncio,
        string $etapaNome,
        ?string $zona,
        ?string $nota,
    ): void {
        $itens = [
            [
                'tipo' => 'sys',
                'titulo' => 'Lead captado via Meta Ads (formulário)',
                'descricao' => trim('Campanha: '.$campanha.($anuncio !== '' ? ' · Anúncio: '.$anuncio : '')),
                'autor' => 'Importação Meta Ads',
                'quando' => $dataEntrada->copy()->setTime(9, 0),
            ],
            [
                'tipo' => 'sys',
                'titulo' => 'Etapa inicial no funil PJ Meta Ads',
                'descricao' => 'Posicionado em “'.$etapaNome.'” com base nas anotações operacionais da planilha.',
                'autor' => 'Importação Meta Ads',
                'quando' => $dataEntrada->copy()->setTime(9, 30),
            ],
        ];

        if ($zona !== null || $nota !== null) {
            $itens[] = [
                'tipo' => 'task',
                'titulo' => 'Anotação operacional importada',
                'descricao' => trim(implode(' · ', array_filter([$zona, $nota]))),
                'autor' => $this->responsavel->name,
                'quando' => $dataEntrada->copy()->addDay()->setTime(11, 0),
            ];
        }

        foreach ($itens as $item) {
            HistoricoNegociacao::query()->create([
                'negociacao_id' => $negociacao->id,
                'tipo' => $item['tipo'],
                'titulo' => $item['titulo'],
                'descricao' => $item['descricao'],
                'autor' => $item['autor'],
                'ocorrido_em' => $item['quando'],
            ]);
        }
    }

    /**
     * @return array{tarefa: ?string, em: ?string, hora: ?string}
     */
    private function proximaTarefa(string $etapaChave, Carbon $dataEntrada): array
    {
        return match ($etapaChave) {
            'novo_lead' => [
                'tarefa' => 'Follow-up 1 — primeiro contato de qualificação (D+1)',
                'em' => $dataEntrada->copy()->addDay()->toDateString(),
                'hora' => '10:00',
            ],
            'follow_up_1', 'follow_up_2', 'follow_up_3', 'follow_up_4' => [
                'tarefa' => 'Próximo follow-up diário de qualificação',
                'em' => $dataEntrada->copy()->addDay()->toDateString(),
                'hora' => '10:00',
            ],
            'follow_up_5' => [
                'tarefa' => 'Decisão: qualificar ou encerrar após 5 tentativas',
                'em' => $dataEntrada->copy()->addDay()->toDateString(),
                'hora' => '11:00',
            ],
            'qualificado' => [
                'tarefa' => 'Agendar reunião de diagnóstico do passivo',
                'em' => $dataEntrada->copy()->addDays(2)->toDateString(),
                'hora' => '14:00',
            ],
            'reuniao_agendada' => [
                'tarefa' => 'Confirmar presença e enviar pauta da reunião',
                'em' => $dataEntrada->copy()->addDay()->toDateString(),
                'hora' => '09:00',
            ],
            'proposta_apresentada' => [
                'tarefa' => 'Follow-up da proposta e objeções',
                'em' => $dataEntrada->copy()->addDays(3)->toDateString(),
                'hora' => '15:00',
            ],
            default => [
                'tarefa' => null,
                'em' => null,
                'hora' => null,
            ],
        };
    }

    private function nomeEmpresa(string $nomeContato, ?string $nota): string
    {
        if ($nota !== null && $this->pareceNomeEmpresa($nota)) {
            return $nota;
        }

        return 'Empresa — '.$nomeContato;
    }

    private function pareceNomeEmpresa(string $valor): bool
    {
        $low = mb_strtolower(trim($valor));

        if ($low === '' || in_array($low, ['n/a', 'na', '-', 'x'], true)) {
            return false;
        }

        if (str_contains($low, 'whatsapp') || str_contains($low, 'retornar') || str_contains($low, 'proposta')) {
            return false;
        }

        if (str_contains($valor, '@')) {
            return false;
        }

        if (preg_match('/\d{1,2}:\d{2}/', $valor) === 1) {
            return false;
        }

        if (preg_match('/^\(.*\)$/', $valor) === 1) {
            return false;
        }

        return true;
    }

    private function assunto(string $campanha, string $anuncio, string $valorDivida): string
    {
        $base = 'Gestão de Passivo PJ';

        if ($anuncio !== '') {
            return $base.' — '.$anuncio;
        }

        if ($valorDivida !== '') {
            return $base.' — '.$valorDivida;
        }

        return $base.' — '.$campanha;
    }

    private function valorNumerico(string $valorDivida): float
    {
        $low = mb_strtolower($valorDivida);

        return match (true) {
            str_contains($low, 'acima') => 600000.0,
            str_contains($low, '300') && str_contains($low, '500') => 400000.0,
            str_contains($low, '100') && str_contains($low, '300') => 200000.0,
            default => 150000.0,
        };
    }

    private function porte(string $valorDivida): string
    {
        $low = mb_strtolower($valorDivida);

        return match (true) {
            str_contains($low, 'acima') => 'Passivo acima de R$ 500k',
            str_contains($low, '300') && str_contains($low, '500') => 'Passivo entre R$ 300k e R$ 500k',
            str_contains($low, '100') && str_contains($low, '300') => 'Passivo entre R$ 100k e R$ 300k',
            default => 'Passivo não informado (Meta Ads)',
        };
    }

    /**
     * @return array{cidade: string, uf_id: int, municipio_id: ?int}
     */
    private function localidadePorTelefone(?string $telefone): array
    {
        $ddd = $telefone !== null && strlen($telefone) >= 10 ? substr($telefone, 0, 2) : '61';

        $mapa = [
            '61' => ['sigla' => 'DF', 'cidade' => 'Brasília'],
            '62' => ['sigla' => 'GO', 'cidade' => 'Goiânia'],
            '51' => ['sigla' => 'RS', 'cidade' => 'Porto Alegre'],
            '53' => ['sigla' => 'RS', 'cidade' => 'Pelotas'],
            '54' => ['sigla' => 'RS', 'cidade' => 'Caxias do Sul'],
            '55' => ['sigla' => 'RS', 'cidade' => 'Santa Maria'],
            '31' => ['sigla' => 'MG', 'cidade' => 'Belo Horizonte'],
            '34' => ['sigla' => 'MG', 'cidade' => 'Uberlândia'],
            '35' => ['sigla' => 'MG', 'cidade' => 'Poços de Caldas'],
            '11' => ['sigla' => 'SP', 'cidade' => 'São Paulo'],
            '19' => ['sigla' => 'SP', 'cidade' => 'Campinas'],
            '21' => ['sigla' => 'RJ', 'cidade' => 'Rio de Janeiro'],
            '41' => ['sigla' => 'PR', 'cidade' => 'Curitiba'],
            '47' => ['sigla' => 'SC', 'cidade' => 'Joinville'],
            '48' => ['sigla' => 'SC', 'cidade' => 'Florianópolis'],
            '71' => ['sigla' => 'BA', 'cidade' => 'Salvador'],
            '81' => ['sigla' => 'PE', 'cidade' => 'Recife'],
            '85' => ['sigla' => 'CE', 'cidade' => 'Fortaleza'],
            '91' => ['sigla' => 'PA', 'cidade' => 'Belém'],
            '92' => ['sigla' => 'AM', 'cidade' => 'Manaus'],
        ];

        $ref = $mapa[$ddd] ?? ['sigla' => 'DF', 'cidade' => 'Brasília'];
        $uf = Uf::query()->where('sigla', $ref['sigla'])->first() ?? $this->ufPadrao;

        $municipio = IbgeMunicipio::query()
            ->where('txt_nome_municipios', $ref['cidade'])
            ->first();

        return [
            'cidade' => $ref['cidade'],
            'uf_id' => $uf->id,
            'municipio_id' => $municipio?->id ?? $this->municipioPadraoId,
        ];
    }

    private function telefone(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $valor) ?? '';

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        return $digits !== '' ? $digits : null;
    }

    private function email(mixed $valor): ?string
    {
        $email = trim((string) ($valor ?? ''));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return mb_strtolower($email);
    }

    private function data(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $valor);
        } catch (\Throwable) {
            return null;
        }
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ''));

        return $texto !== '' ? $texto : null;
    }
}
