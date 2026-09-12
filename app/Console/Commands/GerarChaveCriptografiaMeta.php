<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('meta:gerar-chave {--show : Apenas imprime a chave, sem escrever no .env} {--force : Sobrescreve a chave existente no .env}')]
#[Description('Gera a chave dedicada (META_CAPI_ENCRYPTION_KEY) usada para cifrar os tokens de campanha da CAPI')]
class GerarChaveCriptografiaMeta extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        if ($this->option('show')) {
            $this->line('<comment>'.$this->linhaEnv($key).'</comment>');

            return self::SUCCESS;
        }

        $atual = (string) config('meta.capi.encryption_key');

        if ($atual !== '' && ! $this->option('force')) {
            $this->components->error(
                'META_CAPI_ENCRYPTION_KEY já está definida. Trocar a chave inutiliza os tokens '
                .'já cifrados — rotacione com `php artisan meta:recriptografar-tokens` primeiro, '
                .'ou use --force se o banco ainda não tem campanhas cadastradas.'
            );

            $this->line('Nova chave (cole manualmente se for o caso):');
            $this->line('<comment>'.$this->linhaEnv($key).'</comment>');

            return self::FAILURE;
        }

        if (! $this->escreverNoEnv($key)) {
            $this->components->warn('Não foi possível escrever no .env. Adicione manualmente:');
            $this->line('<comment>'.$this->linhaEnv($key).'</comment>');

            return self::FAILURE;
        }

        config(['meta.capi.encryption_key' => $key]);

        $this->components->info('META_CAPI_ENCRYPTION_KEY definida no arquivo .env.');

        return self::SUCCESS;
    }

    private function linhaEnv(string $key): string
    {
        return 'META_CAPI_ENCRYPTION_KEY='.$key;
    }

    /**
     * Substitui a linha existente ou anexa a variável ao final do .env.
     */
    private function escreverNoEnv(string $key): bool
    {
        $caminho = $this->laravel->environmentFilePath();

        if (! is_file($caminho) || ! is_writable($caminho)) {
            return false;
        }

        $conteudo = file_get_contents($caminho);

        if ($conteudo === false) {
            return false;
        }

        if (Str::contains($conteudo, 'META_CAPI_ENCRYPTION_KEY=')) {
            $conteudo = preg_replace(
                '/^META_CAPI_ENCRYPTION_KEY=.*/m',
                $this->linhaEnv($key),
                $conteudo,
            );
        } else {
            $conteudo = rtrim($conteudo, "\r\n")."\n".$this->linhaEnv($key)."\n";
        }

        return file_put_contents($caminho, $conteudo) !== false;
    }
}
