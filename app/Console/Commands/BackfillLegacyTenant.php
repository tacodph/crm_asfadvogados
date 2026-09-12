<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('tenants:backfill-legacy {--dry-run : Preview the changes without writing them}')]
#[Description('Create the first tenant and backfill tenant_id on legacy pre-multitenant data')]
class BackfillLegacyTenant extends Command
{
    /**
     * Tables to backfill, in an order that keeps foreign-key friendly output readable.
     *
     * @var list<string>
     */
    private array $tables = [
        'setores',
        'canais_contato',
        'status_consentimentos',
        'finalidades_consentimento',
        'status_conflitos',
        'users',
        'empresas',
        'contatos',
        'negociacoes',
        'funis',
        'etapas_funil',
        'historicos_negociacao',
        'consentimentos_contato',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $tenant = Tenant::query()->where('slug', 'asfadvogados')->first();

        if ($tenant === null) {
            $this->info($dryRun
                ? 'Would create tenant [asfadvogados] (ASF Advogados).'
                : 'Creating tenant [asfadvogados] (ASF Advogados)...');
        }

        $counts = [];

        foreach ($this->tables as $table) {
            $counts[$table] = DB::table($table)->whereNull('tenant_id')->count();
        }

        if ($dryRun) {
            foreach ($counts as $table => $count) {
                $this->line("  {$table}: {$count} row(s) would be assigned to tenant [asfadvogados]");
            }

            $this->info('Dry run complete. No changes were written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use (&$tenant, $counts): void {
            $tenant ??= Tenant::query()->create([
                'name' => 'ASF Advogados',
                'slug' => 'asfadvogados',
                'plan' => 'escritorio',
                'status' => 'active',
            ]);

            foreach (array_keys($counts) as $table) {
                DB::table($table)
                    ->whereNull('tenant_id')
                    ->update(['tenant_id' => $tenant->id]);
            }
        });

        foreach ($counts as $table => $count) {
            $this->line("  {$table}: {$count} row(s) assigned to tenant [asfadvogados]");
        }

        $this->info('Backfill complete.');

        return self::SUCCESS;
    }
}
