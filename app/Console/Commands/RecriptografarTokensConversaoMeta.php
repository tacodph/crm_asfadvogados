<?php

namespace App\Console\Commands;

use App\Casts\SegredoMeta;
use App\Models\MetaConversaoConfig;
use App\Models\Tenant;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('meta:recriptografar-tokens
    {--chave-antiga= : Valor de META_CAPI_ENCRYPTION_KEY em uso ao cifrar os tokens atuais}
    {--force : Aplica (sem a flag é dry-run)}')]
#[Description('Rotação da META_CAPI_ENCRYPTION_KEY: decifra os segredos das campanhas com a chave antiga e regrava com a chave atual do config. Nunca imprime tokens.')]
class RecriptografarTokensConversaoMeta extends Command
{
    public function handle(CurrentTenant $tenant): int
    {
        $chaveAntiga = (string) $this->option('chave-antiga');

        if ($chaveAntiga === '') {
            $this->components->error('Informe --chave-antiga com a chave usada para cifrar os tokens atuais.');

            return self::FAILURE;
        }

        try {
            $antigo = SegredoMeta::encrypterParaChave($chaveAntiga);
        } catch (Throwable $e) {
            $this->components->error('Chave antiga inválida: '.$e->getMessage());

            return self::FAILURE;
        }

        $aplicar = (bool) $this->option('force');
        $ok = 0;
        $falhas = 0;

        Tenant::query()->orderBy('id')->each(function (Tenant $t) use ($tenant, $antigo, $aplicar, &$ok, &$falhas): void {
            $tenant->runAs($t, function () use ($antigo, $aplicar, &$ok, &$falhas): void {
                DB::transaction(function () use ($antigo, $aplicar, &$ok, &$falhas): void {
                    MetaConversaoConfig::query()->get()->each(function (MetaConversaoConfig $config) use ($antigo, $aplicar, &$ok, &$falhas): void {
                        try {
                            $this->rotacionar($config, $antigo, $aplicar);
                            $ok++;
                            $this->components->twoColumnDetail($config->slug, $aplicar ? 'recriptografado' : 'ok (dry-run)');
                        } catch (Throwable $e) {
                            $falhas++;
                            $this->components->twoColumnDetail($config->slug, '<error>falhou: '.$e->getMessage().'</error>');
                        }
                    });
                });
            });
        });

        $this->newLine();
        $this->components->info(sprintf(
            '%s — %d ok, %d falha(s).',
            $aplicar ? 'Rotação aplicada' : 'Dry-run (use --force para aplicar)',
            $ok,
            $falhas,
        ));

        return $falhas === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function rotacionar(MetaConversaoConfig $config, Encrypter $antigo, bool $aplicar): void
    {
        foreach (['access_token', 'test_event_code'] as $campo) {
            $cifrado = $config->getRawOriginal($campo);

            if (! is_string($cifrado) || $cifrado === '') {
                continue;
            }

            // Decifra com a chave antiga; a atribuição faz o cast recifrar com a atual.
            $config->{$campo} = $antigo->decryptString($cifrado);
        }

        if ($aplicar && $config->isDirty()) {
            // saveQuietly: a rotação não deve zerar token_verificado_em/token_valido.
            $config->saveQuietly();
        }
    }
}
