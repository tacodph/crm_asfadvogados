<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use RuntimeException;

/**
 * Cifra/decifra um atributo em repouso com a chave dedicada da CAPI
 * (`config('meta.capi.encryption_key')`), independente do `APP_KEY`.
 * Rotacionar o `APP_KEY` não afeta os valores cifrados por este cast.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class SegredoMeta implements CastsAttributes
{
    /**
     * Encrypters já construídos, indexados pela chave crua configurada.
     * Evita reparsear a chave a cada atributo lido/escrito.
     *
     * @var array<string, Encrypter>
     */
    private static array $encrypters = [];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::encrypter()->decryptString((string) $value);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::encrypter()->encryptString((string) $value);
    }

    private static function encrypter(): Encrypter
    {
        $raw = (string) config('meta.capi.encryption_key');

        if ($raw === '') {
            throw new RuntimeException(
                'META_CAPI_ENCRYPTION_KEY não configurada. Rode `php artisan meta:gerar-chave`.'
            );
        }

        return self::encrypterParaChave($raw);
    }

    /**
     * Encrypter para uma chave crua arbitrária — usado pela rotação de chave
     * (`meta:recriptografar-tokens`) para decifrar com a chave antiga.
     */
    public static function encrypterParaChave(string $raw): Encrypter
    {
        return self::$encrypters[$raw] ??= new Encrypter(self::parseKey($raw), 'AES-256-CBC');
    }

    private static function parseKey(string $raw): string
    {
        $key = str_starts_with($raw, 'base64:')
            ? (base64_decode(substr($raw, 7), true) ?: '')
            : $raw;

        if (strlen($key) !== 32) {
            throw new RuntimeException(
                'META_CAPI_ENCRYPTION_KEY inválida: esperado `base64:` de 32 bytes (256 bits).'
            );
        }

        return $key;
    }
}
