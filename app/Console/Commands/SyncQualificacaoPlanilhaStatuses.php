<?php

namespace App\Console\Commands;

use App\Actions\Tenancy\SeedDefaultCatalogsForTenant;
use App\Models\Contato;
use App\Models\Negociacao;
use App\Models\StatusAtendimento;
use App\Models\StatusQualificacao;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SyncQualificacaoPlanilhaStatuses extends Command
{
    protected $signature = 'crm:sync-qualificacao-planilha-status
        {--json= : Caminho do JSON exportado da planilha}
        {--tenant=asfadvogados : Slug do tenant}';

    protected $description = 'Atualiza status de atendimento/qualificação das negociações a partir da planilha (JSON).';

    public function handle(SeedDefaultCatalogsForTenant $seedCatalogs): int
    {
        $tenant = Tenant::query()->where('slug', (string) $this->option('tenant'))->first();

        if ($tenant === null) {
            $this->error('Tenant não encontrado.');

            return self::FAILURE;
        }

        $path = (string) ($this->option('json') ?: database_path('data/qualificacao-atendimentos-agosto.json'));

        if (! File::exists($path)) {
            $this->error("Arquivo não encontrado: {$path}");

            return self::FAILURE;
        }

        /** @var list<array<string, mixed>> $linhas */
        $linhas = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);

        $seedCatalogs($tenant);

        $atualizados = 0;
        $naoEncontrados = 0;

        app(CurrentTenant::class)->runAs($tenant, function () use ($linhas, &$atualizados, &$naoEncontrados): void {
            $atendimentos = StatusAtendimento::query()->get()->keyBy(
                fn (StatusAtendimento $status): string => $this->chave($status->nome),
            );
            $qualificacoes = StatusQualificacao::query()->get()->keyBy(
                fn (StatusQualificacao $status): string => $this->chave($status->nome),
            );

            foreach ($linhas as $linha) {
                $telefone = $this->telefone($linha['ddd'] ?? null, $linha['whatsapp'] ?? null);
                $nome = trim((string) ($linha['nome'] ?? ''));

                $contato = null;

                if ($telefone !== null) {
                    $contato = Contato::query()->where('telefone', $telefone)->first();
                }

                if ($contato === null && $nome !== '') {
                    $contato = Contato::query()->where('nome', $nome)->first();
                }

                if ($contato === null) {
                    $naoEncontrados++;

                    continue;
                }

                $negociacao = Negociacao::query()
                    ->where('contato_id', $contato->id)
                    ->orderByDesc('id')
                    ->first();

                if ($negociacao === null) {
                    $naoEncontrados++;

                    continue;
                }

                $statusAtendimentoNome = $this->texto($linha['status_atendimento'] ?? null);
                $statusQualificacaoNome = $this->texto($linha['status_qualificacao'] ?? null);

                $statusAtendimento = $statusAtendimentoNome !== null
                    ? $atendimentos->get($this->chave($statusAtendimentoNome))
                    : null;
                $statusQualificacao = $statusQualificacaoNome !== null
                    ? $qualificacoes->get($this->chave($statusQualificacaoNome))
                    : null;

                if ($statusAtendimento === null && $statusAtendimentoNome !== null) {
                    $statusAtendimento = StatusAtendimento::query()->updateOrCreate(
                        [
                            'tenant_id' => $negociacao->tenant_id,
                            'slug' => Str::slug($statusAtendimentoNome),
                        ],
                        [
                            'nome' => $statusAtendimentoNome,
                            'cor_fundo' => '#F4F2EC',
                            'cor_texto' => '#77808E',
                            'ordem' => 99,
                        ],
                    );
                    $atendimentos->put($this->chave($statusAtendimento->nome), $statusAtendimento);
                }

                if ($statusQualificacao === null && $statusQualificacaoNome !== null) {
                    $statusQualificacao = StatusQualificacao::query()->updateOrCreate(
                        [
                            'tenant_id' => $negociacao->tenant_id,
                            'slug' => Str::slug($statusQualificacaoNome),
                        ],
                        [
                            'nome' => $statusQualificacaoNome,
                            'cor_fundo' => '#F4F2EC',
                            'cor_texto' => '#77808E',
                            'ordem' => 99,
                        ],
                    );
                    $qualificacoes->put($this->chave($statusQualificacao->nome), $statusQualificacao);
                }

                $negociacao->forceFill([
                    'status_atendimento_id' => $statusAtendimento?->id,
                    'status_qualificacao_id' => $statusQualificacao?->id,
                    'motivo_desqualificacao' => $this->texto($linha['motivo_desqualificacao'] ?? null),
                    'continuidade_atendimento' => $this->texto($linha['continuidade'] ?? null),
                    'observacoes_complementares' => $this->texto($linha['observacoes'] ?? null),
                ])->save();

                $atualizados++;
            }
        });

        $this->info("Atualizados: {$atualizados}. Não encontrados: {$naoEncontrados}.");

        return self::SUCCESS;
    }

    private function chave(string $texto): string
    {
        return Str::of($texto)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->trim()
            ->toString();
    }

    private function texto(mixed $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private function telefone(mixed $ddd, mixed $whatsapp): ?string
    {
        $dddDigits = preg_replace('/\D+/', '', (string) ($ddd ?? '')) ?? '';
        $foneDigits = preg_replace('/\D+/', '', (string) ($whatsapp ?? '')) ?? '';

        if ($foneDigits === '') {
            return null;
        }

        if ($dddDigits !== '' && ! str_starts_with($foneDigits, $dddDigits)) {
            $foneDigits = $dddDigits.$foneDigits;
        }

        return $foneDigits;
    }
}
