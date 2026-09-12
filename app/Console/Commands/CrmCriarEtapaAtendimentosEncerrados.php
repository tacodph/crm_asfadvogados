<?php

namespace App\Console\Commands;

use App\Actions\Crm\MoverNegociacoesEncerradasParaEtapa;
use App\Models\Funil;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Command;

class CrmCriarEtapaAtendimentosEncerrados extends Command
{
    protected $signature = 'crm:criar-etapa-atendimentos-encerrados
        {--tenant= : Slug do tenant (omitir = todos)}
        {--dry-run : Só mostra o que seria feito}';

    protected $description = 'Cria a etapa vermelha “Atendimentos encerrados” e move leads Encerrado/Desqualificado.';

    public function handle(MoverNegociacoesEncerradasParaEtapa $mover): int
    {
        $slug = $this->option('tenant');

        $tenants = Tenant::query()
            ->when(
                is_string($slug) && $slug !== '',
                fn ($q) => $q->where('slug', $slug),
            )
            ->orderBy('id')
            ->get();

        if ($tenants->isEmpty()) {
            $this->error('Nenhum tenant encontrado.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            app(CurrentTenant::class)->runAs($tenant, function () use ($tenant, $mover): void {
                $this->info("Tenant: {$tenant->slug}");

                $funis = Funil::query()->orderBy('ordem')->get();

                if ($funis->isEmpty()) {
                    $this->warn('  Sem funis.');

                    return;
                }

                foreach ($funis as $funil) {
                    if ($this->option('dry-run')) {
                        $count = $funil->negociacoes()
                            ->where(function ($query): void {
                                $query->whereHas('statusAtendimento', fn ($q) => $q->where('slug', 'encerrado'))
                                    ->orWhereHas('statusQualificacao', fn ($q) => $q->where('slug', 'desqualificado'));
                            })
                            ->count();

                        $this->line("  Funil {$funil->slug}: ~{$count} candidatas (dry-run)");

                        continue;
                    }

                    $resultado = $mover($funil);

                    $this->line(sprintf(
                        '  Funil %s: etapa #%d · movidas=%d · já na etapa=%d',
                        $funil->slug,
                        $resultado['etapa_id'],
                        $resultado['movidas'],
                        $resultado['ja_na_etapa'],
                    ));
                }
            });
        }

        return self::SUCCESS;
    }
}
