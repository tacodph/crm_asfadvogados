<?php

namespace Database\Seeders;

use App\Enums\StatusProposta;
use App\Enums\StatusTarefaNegociacao;
use App\Models\CanalContato;
use App\Models\ConsentimentoContato;
use App\Models\Contato;
use App\Models\EtapaFunil;
use App\Models\FinalidadeConsentimento;
use App\Models\Funil;
use App\Models\HistoricoNegociacao;
use App\Models\Negociacao;
use App\Models\Proposta;
use App\Models\Role;
use App\Models\StatusAtendimento;
use App\Models\StatusComercial;
use App\Models\StatusConsentimento;
use App\Models\StatusQualificacao;
use App\Models\TarefaNegociacao;
use App\Models\TipoPessoa;
use App\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Database\Seeders\Concerns\SeedsForDevTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Importa os atendimentos reais da planilha "Qualificação de Atendimentos"
 * (aba Agosto) para o funil PF / Concursos. Não cria empresas (todos PF).
 *
 * Fonte: database/data/qualificacao-atendimentos-agosto.json
 */
class QualificacaoAtendimentosSeeder extends Seeder
{
    use SeedsForDevTenant;

    /**
     * Optional override used by tests (absolute or relative path to JSON).
     */
    public string $dataPath = '';

    /**
     * @var array<string, User>
     */
    private array $responsaveis = [];

    /**
     * @var array<string, StatusComercial>
     */
    private array $statusComerciais = [];

    /**
     * @var array<string, StatusAtendimento>
     */
    private array $statusAtendimentos = [];

    /**
     * @var array<string, StatusQualificacao>
     */
    private array $statusQualificacoes = [];

    /**
     * @var array<int, EtapaFunil>
     */
    private array $etapasPorOrdem = [];

    private CanalContato $canalWhatsapp;

    private TipoPessoa $tipoPf;

    private StatusConsentimento $consentOptIn;

    private StatusConsentimento $consentPendente;

    private FinalidadeConsentimento $finalidadeContato;

    private FinalidadeConsentimento $finalidadeMensagens;

    private int $propostaSeq = 0;

    public function run(): void
    {
        $this->call(DominioCrmSeeder::class);

        app(CurrentTenant::class)->runAs($this->devTenant(), function (): void {
            $this->ensureFunilPf();
            $this->carregarCatalogos();
            $this->ensureResponsaveis();

            foreach ($this->linhas() as $index => $linha) {
                $this->importarLinha($linha, $index + 1);
            }
        });
    }

    /**
     * @return list<array{
     *     data_entrada: ?string,
     *     nome: ?string,
     *     ddd: mixed,
     *     whatsapp: mixed,
     *     campanha: ?string,
     *     responsavel: ?string,
     *     respondeu: ?string,
     *     proposta: ?string,
     *     fu1: ?string,
     *     fu2: ?string,
     *     fu3: ?string,
     *     status_atendimento: ?string,
     *     status_qualificacao: ?string,
     *     motivo_desqualificacao: ?string,
     *     continuidade: ?string,
     *     observacoes: ?string
     * }>
     */
    private function linhas(): array
    {
        $path = $this->dataPath !== ''
            ? $this->dataPath
            : database_path('data/qualificacao-atendimentos-agosto.json');

        if (! File::isFile($path)) {
            throw new RuntimeException("Arquivo de importação não encontrado: {$path}");
        }

        /** @var list<array<string, mixed>> $linhas */
        $linhas = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        return $linhas;
    }

    private function ensureFunilPf(): void
    {
        $paleta = ['#333A45', '#31496E', '#41503A', '#6B5330', '#6B3852'];

        $funil = Funil::query()->updateOrCreate(
            ['slug' => 'pf'],
            [
                'nome' => 'Concursos (PF, volume)',
                'distribuicao' => 'round robin simples',
                'ordem' => 2,
            ],
        );

        $etapas = [
            ['nome' => 'Novo lead', 'sla' => '15min', 'campos' => ['telefone válido', 'consentimento'], 'exige' => false, 'resultado' => 'aberta'],
            ['nome' => 'Triagem', 'sla' => '1d', 'campos' => ['concurso/banca', 'fase'], 'exige' => true, 'resultado' => 'aberta'],
            ['nome' => 'Envio da oferta', 'sla' => '2d', 'campos' => ['oferta registrada'], 'exige' => true, 'resultado' => 'aberta'],
            ['nome' => 'Objeções', 'sla' => '3d', 'campos' => ['objeção classificada'], 'exige' => true, 'resultado' => 'aberta'],
            ['nome' => 'Fechamento', 'sla' => '—', 'campos' => ['contrato assinado'], 'exige' => false, 'resultado' => 'ganho'],
            ['nome' => 'Atendimentos encerrados', 'sla' => '—', 'campos' => ['motivo do encerramento', 'desqualificação'], 'exige' => true, 'resultado' => 'perdido'],
        ];

        foreach ($etapas as $i => $etapa) {
            $resultado = $etapa['resultado'];
            $ganho = $resultado === 'ganho';
            $perdido = $resultado === 'perdido';

            EtapaFunil::query()->updateOrCreate(
                [
                    'funil_id' => $funil->id,
                    'ordem' => $i + 1,
                ],
                [
                    'nome' => $etapa['nome'],
                    'sla' => $etapa['sla'],
                    'campos' => $etapa['campos'],
                    'exige_motivo' => $etapa['exige'],
                    'resultado' => $resultado,
                    'cor_fundo' => $perdido
                        ? '#9B3B2F'
                        : ($ganho ? '#0F4A43' : $paleta[$i % count($paleta)]),
                    'cor_texto' => '#FBF9F4',
                    'cor_suave' => $perdido
                        ? 'rgba(251,249,244,0.92)'
                        : 'rgba(251,249,244,0.9)',
                ],
            );
        }

        $this->etapasPorOrdem = $funil->etapas()->orderBy('ordem')->get()->keyBy('ordem')->all();
    }

    private function carregarCatalogos(): void
    {
        $this->canalWhatsapp = CanalContato::query()->where('slug', 'whatsapp')->firstOrFail();
        $this->tipoPf = TipoPessoa::query()->where('slug', 'pf')->firstOrFail();
        $this->consentOptIn = StatusConsentimento::query()->where('slug', 'opt-in-registrado')->firstOrFail();
        $this->consentPendente = StatusConsentimento::query()->where('slug', 'pendente')->firstOrFail();
        $this->finalidadeContato = FinalidadeConsentimento::query()->where('slug', 'contato-comercial')->firstOrFail();
        $this->finalidadeMensagens = FinalidadeConsentimento::query()->where('slug', 'mensagens')->firstOrFail();

        $this->statusComerciais = StatusComercial::query()
            ->get()
            ->keyBy('slug')
            ->all();

        $this->statusAtendimentos = StatusAtendimento::query()
            ->get()
            ->keyBy(fn (StatusAtendimento $status): string => $this->chaveStatus($status->nome))
            ->all();

        $this->statusQualificacoes = StatusQualificacao::query()
            ->get()
            ->keyBy(fn (StatusQualificacao $status): string => $this->chaveStatus($status->nome))
            ->all();
    }

    private function ensureResponsaveis(): void
    {
        $defs = [
            'Dr. Bruno Gabriel' => [
                'email' => 'bruno.gabriel@asfadvogados.adv.br',
                'name' => 'Dr. Bruno Gabriel',
            ],
            'Dr. Flávio Augusto' => [
                'email' => 'flavio.augusto@asfadvogados.adv.br',
                'name' => 'Dr. Flávio Augusto',
            ],
        ];

        foreach ($defs as $chave => $dados) {
            $user = User::query()->updateOrCreate(
                ['email' => $dados['email']],
                [
                    'name' => $dados['name'],
                    'password' => 'password',
                    'role' => Role::VENDEDOR,
                    'especialidades' => ['concursos'],
                    'ausente_ate' => null,
                ],
            );

            $this->responsaveis[$this->normalizarTexto($chave)] = $user;
        }
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    private function importarLinha(array $linha, int $ordem): void
    {
        $dataEntrada = $this->data($linha['data_entrada'] ?? null) ?? Carbon::parse('2026-08-05');
        $nome = trim((string) ($linha['nome'] ?? '')) ?: 'Lead sem nome';
        $telefone = $this->telefone($linha['ddd'] ?? null, $linha['whatsapp'] ?? null);
        $campanha = trim((string) ($linha['campanha'] ?? '')) ?: 'Campanha não informada';
        $responsavel = $this->resolverResponsavel((string) ($linha['responsavel'] ?? 'Dr. Bruno Gabriel'));
        $respondeu = $this->sim($linha['respondeu'] ?? null);
        $propostaSim = $this->sim($linha['proposta'] ?? null);
        $statusAtendimento = $this->normalizarTexto($linha['status_atendimento'] ?? null);
        $statusQualificacao = $this->normalizarTexto($linha['status_qualificacao'] ?? null);
        $motivo = $this->textoOpcional($linha['motivo_desqualificacao'] ?? null);
        $continuidade = $this->textoOpcional($linha['continuidade'] ?? null);
        $observacoes = $this->textoOpcional($linha['observacoes'] ?? null);

        $statusComercial = $this->resolverStatusComercial($statusQualificacao, $statusAtendimento);
        $statusAtendimentoModel = $this->resolverStatusAtendimento($statusAtendimento);
        $statusQualificacaoModel = $this->resolverStatusQualificacao($statusQualificacao);
        $consentimento = $respondeu ? $this->consentOptIn : $this->consentPendente;
        $etapaOrdem = $this->resolverEtapaOrdem($statusAtendimento, $propostaSim, $statusQualificacao);
        $etapa = $this->etapasPorOrdem[$etapaOrdem];
        $funilId = $etapa->funil_id;

        $contato = Contato::query()->create([
            'nome' => $nome,
            'cargo' => 'Candidato — '.$campanha,
            'empresa_id' => null,
            'tipo_pessoa_id' => $this->tipoPf->id,
            'email' => null,
            'telefone' => $telefone,
            'cpf' => null,
            'canal_contato_id' => $this->canalWhatsapp->id,
            'status_consentimento_id' => $consentimento->id,
            'status_comercial_id' => $statusComercial->id,
            'registro_mesclado' => false,
            'observacao_deduplicacao' => 'Importação planilha Agosto/2026 · linha '.$ordem,
        ]);

        $this->criarConsentimentos($contato, $consentimento, $dataEntrada);

        $proximaTarefa = null;
        $proximaEm = null;
        $proximaHora = null;

        if ($statusAtendimento === 'retorno a agendar') {
            $proximaTarefa = 'Retorno a agendar — follow-up WhatsApp';
            $proximaEm = $dataEntrada->copy()->addDays(2)->toDateString();
            $proximaHora = '10:00';
        } elseif ($statusAtendimento === 'atendimento em andamento') {
            $proximaTarefa = 'Continuar atendimento em andamento';
            $proximaEm = $dataEntrada->copy()->addDay()->toDateString();
            $proximaHora = '14:00';
        }

        $negociacao = Negociacao::withoutEvents(function () use (
            $funilId,
            $etapa,
            $contato,
            $responsavel,
            $campanha,
            $statusAtendimento,
            $statusAtendimentoModel,
            $statusQualificacaoModel,
            $motivo,
            $continuidade,
            $observacoes,
            $dataEntrada,
            $proximaTarefa,
            $proximaEm,
            $proximaHora,
        ): Negociacao {
            $negociacao = new Negociacao([
                'funil_id' => $funilId,
                'etapa_funil_id' => $etapa->id,
                'empresa_id' => null,
                'contato_id' => $contato->id,
                'canal_contato_id' => $this->canalWhatsapp->id,
                'status_atendimento_id' => $statusAtendimentoModel?->id,
                'status_qualificacao_id' => $statusQualificacaoModel?->id,
                'motivo_desqualificacao' => $motivo,
                'continuidade_atendimento' => $continuidade,
                'observacoes_complementares' => $observacoes,
                'responsavel_user_id' => $responsavel->id,
                'assunto' => $campanha,
                'valor' => 0,
                'previsao_fechamento' => $statusAtendimento === 'contrato fechado'
                    ? $dataEntrada->toDateString()
                    : $dataEntrada->copy()->addDays(14)->toDateString(),
                'etapa_desde' => $dataEntrada->copy()->setTime(9, 0),
                'proxima_tarefa' => $proximaTarefa,
                'proxima_tarefa_em' => $proximaEm,
                'proxima_tarefa_hora' => $proximaHora,
            ]);

            // withoutEvents desliga o boot BelongsToTenant; preenche manualmente.
            $negociacao->tenant_id = app(CurrentTenant::class)->id();
            $negociacao->created_at = $dataEntrada->copy()->setTime(9, 0);
            $negociacao->updated_at = $dataEntrada->copy()->setTime(9, 0);
            $negociacao->save();

            return $negociacao;
        });

        $this->criarHistoricos(
            $negociacao,
            $responsavel,
            $dataEntrada,
            $respondeu,
            $propostaSim,
            $statusAtendimento,
            $statusQualificacao,
            $motivo,
            $continuidade,
            $observacoes,
            $linha,
        );

        if ($proximaTarefa !== null && $proximaEm !== null) {
            TarefaNegociacao::query()->create([
                'negociacao_id' => $negociacao->id,
                'descricao' => $proximaTarefa,
                'data' => $proximaEm,
                'hora' => $proximaHora,
                'status' => StatusTarefaNegociacao::Pendente,
                'criado_por_user_id' => $responsavel->id,
            ]);
        }

        if ($propostaSim) {
            $this->criarProposta($negociacao, $responsavel, $dataEntrada, $statusAtendimento, $campanha);
        }
    }

    private function criarConsentimentos(Contato $contato, StatusConsentimento $status, Carbon $dataEntrada): void
    {
        foreach ([$this->finalidadeContato, $this->finalidadeMensagens] as $finalidade) {
            ConsentimentoContato::query()->create([
                'contato_id' => $contato->id,
                'finalidade_consentimento_id' => $finalidade->id,
                'status_consentimento_id' => $status->id,
                'concedido_em' => $status->slug === 'opt-in-registrado' ? $dataEntrada->toDateString() : null,
                'revogado_em' => null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    private function criarHistoricos(
        Negociacao $negociacao,
        User $responsavel,
        Carbon $dataEntrada,
        bool $respondeu,
        bool $propostaSim,
        string $statusAtendimento,
        ?string $statusQualificacao,
        ?string $motivo,
        ?string $continuidade,
        ?string $observacoes,
        array $linha,
    ): void {
        $itens = [
            [
                'tipo' => 'sys',
                'titulo' => 'Lead captado (WhatsApp / anúncio)',
                'descricao' => 'Entrada registrada na planilha de qualificação de atendimentos (Agosto/2026).',
                'autor' => 'Importação',
                'quando' => $dataEntrada->copy()->setTime(9, 0),
            ],
            [
                'tipo' => 'wa',
                'titulo' => $respondeu ? 'Lead respondeu no WhatsApp' : 'Sem resposta no primeiro contato',
                'descricao' => $respondeu
                    ? 'Respondeu = Sim na planilha.'
                    : 'Respondeu = Não na planilha.',
                'autor' => $responsavel->name,
                'quando' => $dataEntrada->copy()->setTime(10, 0),
            ],
        ];

        foreach (['fu1' => '1º', 'fu2' => '2º', 'fu3' => '3º'] as $campo => $rotulo) {
            $fu = $this->data($linha[$campo] ?? null);

            if ($fu === null) {
                continue;
            }

            $itens[] = [
                'tipo' => 'task',
                'titulo' => "Follow-up {$rotulo} registrado",
                'descricao' => "Data do follow-up na planilha: {$fu->toDateString()}.",
                'autor' => $responsavel->name,
                'quando' => $fu->copy()->setTime(11, 0),
            ];
        }

        if ($propostaSim) {
            $itens[] = [
                'tipo' => 'mail',
                'titulo' => 'Proposta apresentada',
                'descricao' => 'Proposta = Sim na planilha de qualificação.',
                'autor' => $responsavel->name,
                'quando' => $dataEntrada->copy()->addDay()->setTime(15, 0),
            ];
        }

        $resumo = trim(implode(' · ', array_filter([
            $statusAtendimento !== '' ? 'Atendimento: '.$this->tituloStatus($statusAtendimento) : null,
            $statusQualificacao !== null ? 'Qualificação: '.$this->tituloStatus($statusQualificacao) : null,
            $motivo,
            $continuidade !== null ? 'Continuidade: '.$continuidade : null,
            $observacoes,
        ])));

        if ($resumo !== '') {
            $itens[] = [
                'tipo' => 'sys',
                'titulo' => 'Resumo da planilha',
                'descricao' => $resumo,
                'autor' => 'Importação',
                'quando' => $dataEntrada->copy()->addDays(2)->setTime(18, 0),
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

    private function criarProposta(
        Negociacao $negociacao,
        User $responsavel,
        Carbon $dataEntrada,
        string $statusAtendimento,
        string $campanha,
    ): void {
        $this->propostaSeq++;
        $codigo = sprintf('PR-AGO-%03d', $this->propostaSeq);
        $fechado = $statusAtendimento === 'contrato fechado';

        Proposta::query()->create([
            'negociacao_id' => $negociacao->id,
            'contato_id' => $negociacao->contato_id,
            'empresa_id' => null,
            'autor_user_id' => $responsavel->id,
            'codigo' => $codigo,
            'versao' => 1,
            'titulo' => $campanha,
            'escopo' => 'Proposta apresentada no atendimento de concurso (importação Agosto/2026). Detalhes financeiros não constavam na planilha.',
            'honorarios' => 0,
            'parcelamento' => null,
            'indice_reajuste' => null,
            'modelo_origem' => 'Importação planilha Agosto/2026',
            'status' => $fechado ? StatusProposta::Aceita : StatusProposta::Enviada,
            'valido_ate' => $dataEntrada->copy()->addDays(10)->toDateString(),
            'enviado_em' => $dataEntrada->copy()->addDay()->setTime(15, 0),
            'aceito_em' => $fechado ? $dataEntrada->copy()->addDays(3)->setTime(16, 0) : null,
            'clausulas' => [
                [
                    'titulo' => 'Origem',
                    'texto' => 'Registro criado a partir da planilha de qualificação de atendimentos; valores a complementar no CRM.',
                ],
            ],
        ]);
    }

    private function resolverStatusComercial(?string $statusQualificacao, string $statusAtendimento): StatusComercial
    {
        if ($statusAtendimento === 'contrato fechado') {
            return $this->statusComerciais['cliente-efetivado'];
        }

        if ($statusAtendimento === 'em negociacao') {
            return $this->statusComerciais['em-negociacao'];
        }

        if ($statusQualificacao !== null && str_starts_with($statusQualificacao, 'aguardando retorno')) {
            return $this->statusComerciais['em-analise'];
        }

        return match ($statusQualificacao) {
            'qualificado' => $this->statusComerciais['qualificado'],
            'desqualificado' => $this->statusComerciais['desqualificado'],
            default => $this->statusComerciais['novo'],
        };
    }

    private function resolverEtapaOrdem(string $statusAtendimento, bool $propostaSim, ?string $statusQualificacao): int
    {
        if ($statusAtendimento === 'contrato fechado') {
            return 5;
        }

        if ($statusAtendimento === 'encerrado' || $statusQualificacao === 'desqualificado') {
            return 6;
        }

        if ($statusAtendimento === 'em negociacao') {
            return 4;
        }

        if ($propostaSim) {
            return $statusAtendimento === 'retorno a agendar' ? 3 : 3;
        }

        if (in_array($statusAtendimento, ['analise pendente', 'atendimento em andamento'], true)) {
            return $statusAtendimento === 'analise pendente' ? 1 : 2;
        }

        if ($statusQualificacao !== null && str_starts_with($statusQualificacao, 'aguardando retorno')) {
            return 1;
        }

        // Retorno a agendar sem proposta → triagem.
        return 2;
    }

    private function resolverResponsavel(string $nome): User
    {
        $chave = $this->normalizarTexto($nome);

        return $this->responsaveis[$chave]
            ?? $this->responsaveis[$this->normalizarTexto('Dr. Bruno Gabriel')];
    }

    private function telefone(mixed $ddd, mixed $whatsapp): ?string
    {
        $whatsappDigits = preg_replace('/\D+/', '', (string) $whatsapp) ?? '';
        $dddRaw = trim((string) $ddd);
        $dddDigits = preg_replace('/\D+/', '', $dddRaw) ?? '';

        if ($dddDigits === '' || strcasecmp($dddRaw, 'None') === 0) {
            return $whatsappDigits !== '' ? $whatsappDigits : null;
        }

        if ($whatsappDigits !== '' && str_starts_with($whatsappDigits, $dddDigits)) {
            return $whatsappDigits;
        }

        $completo = $dddDigits.$whatsappDigits;

        return $completo !== '' ? $completo : null;
    }

    private function data(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        return Carbon::parse((string) $valor)->startOfDay();
    }

    private function sim(mixed $valor): bool
    {
        $texto = mb_strtolower(trim((string) $valor));

        return in_array($texto, ['sim', 's', 'yes', 'true', '1'], true);
    }

    private function normalizarTexto(mixed $valor): string
    {
        $texto = mb_strtolower(trim((string) $valor));
        $texto = str_replace(['á', 'à', 'ã', 'â', 'é', 'ê', 'í', 'ó', 'ô', 'õ', 'ú', 'ç', 'º'], ['a', 'a', 'a', 'a', 'e', 'e', 'i', 'o', 'o', 'o', 'u', 'c', 'o'], $texto);
        $texto = preg_replace('/\s+/', ' ', $texto) ?? $texto;

        return $texto;
    }

    private function chaveStatus(string $texto): string
    {
        return $this->normalizarTexto($texto);
    }

    private function resolverStatusAtendimento(string $statusAtendimento): ?StatusAtendimento
    {
        if ($statusAtendimento === '') {
            return null;
        }

        return $this->statusAtendimentos[$this->chaveStatus($statusAtendimento)] ?? null;
    }

    private function resolverStatusQualificacao(?string $statusQualificacao): ?StatusQualificacao
    {
        if ($statusQualificacao === null || $statusQualificacao === '') {
            return null;
        }

        return $this->statusQualificacoes[$this->chaveStatus($statusQualificacao)] ?? null;
    }

    private function tituloStatus(string $normalizado): string
    {
        return mb_convert_case($normalizado, MB_CASE_TITLE, 'UTF-8');
    }

    private function textoOpcional(mixed $valor): ?string
    {
        $texto = trim((string) $valor);

        if ($texto === '' || strcasecmp($texto, 'None') === 0) {
            return null;
        }

        // Marcador da planilha para leads já qualificados.
        if (str_contains(mb_strtolower($texto), 'não registrar')) {
            return null;
        }

        return $texto;
    }
}
