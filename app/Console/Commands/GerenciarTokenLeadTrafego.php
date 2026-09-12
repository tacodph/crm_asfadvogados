<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('meta:trafego-token {--tenant= : slug do tenant (obrigatório se houver mais de um)} {--rotacionar : gera um novo token mesmo que já exista}')]
#[Description('Mostra/gera o segredo de captação de leads pelo site (POST /api/trafego/leads)')]
class GerenciarTokenLeadTrafego extends Command
{
    public function handle(): int
    {
        $tenant = $this->resolverTenant();

        if (! $tenant instanceof Tenant) {
            return self::FAILURE;
        }

        $url = rtrim((string) config('app.url'), '/').'/api/trafego/leads';
        $this->line("Endpoint: <comment>POST {$url}</comment>");

        if ($tenant->trafego_lead_token_hash !== null && ! $this->option('rotacionar')) {
            $this->line('Token atual: <comment>'.$tenant->mascararTokenLeadTrafego().'</comment>'
                .' (gerado em '.($tenant->trafego_lead_token_gerado_em?->format('d/m/Y H:i') ?? '—').')');
            $this->components->warn('O valor cru não é recuperável. Use --rotacionar para gerar um novo.');

            return self::SUCCESS;
        }

        $raw = $tenant->gerarTokenLeadTrafego();

        $this->newLine();
        $this->components->info("Token de {$tenant->name} — copie agora, não será mostrado de novo:");
        $this->line("<comment>{$raw}</comment>");
        $this->newLine();
        $this->line('Envie em cada request como header:');
        $this->line("  <comment>Authorization: Bearer {$raw}</comment>");

        return self::SUCCESS;
    }

    private function resolverTenant(): ?Tenant
    {
        $slug = $this->option('tenant');

        if (is_string($slug) && $slug !== '') {
            $tenant = Tenant::query()->where('slug', $slug)->first();

            if (! $tenant instanceof Tenant) {
                $this->components->error("Tenant \"{$slug}\" não encontrado.");
            }

            return $tenant;
        }

        $tenants = Tenant::query()->orderBy('id')->get();

        if ($tenants->count() === 1) {
            return $tenants->first();
        }

        $this->components->error('Há mais de um tenant — informe --tenant=<slug>.');

        return null;
    }
}
