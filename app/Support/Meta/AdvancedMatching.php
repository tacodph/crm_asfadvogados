<?php

namespace App\Support\Meta;

use App\Models\Contato;
use App\Models\Empresa;

/**
 * Normalização + SHA-256 dos parâmetros de Advanced Matching da Meta.
 * Nenhum dado pessoal sai do CRM sem passar por aqui.
 *
 * Regras da Meta: minúsculas, sem espaços nas bordas, telefone só dígitos
 * com código de país, e-mail inteiro em minúsculas. Cada campo hasheado vai
 * como array de 1 elemento: `"em": ["<hash>"]`.
 */
final class AdvancedMatching
{
    /**
     * Estrutura `user_data` pronta para o payload da CAPI, sem chaves nulas.
     *
     * @param  array<string, string>  $browser  fbp/fbc/client_ip_address/client_user_agent em texto puro
     * @param  Empresa|null  $empresa  fallback de cidade/UF quando o contato (PJ) não tem
     * @return array<string, mixed>
     */
    public static function userDataFrom(Contato $contato, array $browser = [], ?Empresa $empresa = null): array
    {
        [$primeiroNome, $ultimoNome] = self::dividirNome($contato->nome);

        $cidade = $contato->cidade ?? $empresa?->cidade;

        $uf = $contato->uf?->sigla;

        if (($uf === null || $uf === '') && $empresa !== null) {
            $uf = $empresa->uf?->sigla;
        }

        $dados = [
            'em' => self::comoArray(self::email($contato->email)),
            'ph' => self::comoArray(self::phone($contato->telefone)),
            'fn' => self::comoArray(self::name($primeiroNome)),
            'ln' => self::comoArray(self::name($ultimoNome)),
            'ct' => self::comoArray(self::city($cidade)),
            'st' => self::comoArray(self::state($uf)),
            'zp' => self::comoArray(self::zip($contato->cep)),
            'country' => self::comoArray(self::country()),
            'external_id' => self::comoArray(self::externalId($contato->id)),
        ];

        foreach (['client_ip_address', 'client_user_agent', 'fbc', 'fbp'] as $campo) {
            $valor = $browser[$campo] ?? null;

            if (is_string($valor) && $valor !== '') {
                $dados[$campo] = $valor;
            }
        }

        return array_filter($dados, static fn ($valor): bool => $valor !== null);
    }

    public static function hash(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalizado = mb_strtolower(trim($value));

        return $normalizado === '' ? null : hash('sha256', $normalizado);
    }

    public static function email(?string $email): ?string
    {
        return self::hash($email);
    }

    public static function name(?string $nome): ?string
    {
        return self::hash($nome);
    }

    public static function phone(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digitos === '') {
            return null;
        }

        // 10–11 dígitos ⇒ número BR sem DDI; prefixa 55.
        if (strlen($digitos) <= 11) {
            $digitos = '55'.$digitos;
        }

        return hash('sha256', $digitos);
    }

    public static function zip(?string $zip): ?string
    {
        if ($zip === null) {
            return null;
        }

        $digitos = substr(preg_replace('/\D+/', '', $zip) ?? '', 0, 5);

        return $digitos === '' ? null : hash('sha256', $digitos);
    }

    public static function state(?string $uf): ?string
    {
        if ($uf === null) {
            return null;
        }

        $normalizado = mb_strtolower(trim($uf));

        return $normalizado === '' ? null : hash('sha256', $normalizado);
    }

    public static function city(?string $cidade): ?string
    {
        if ($cidade === null) {
            return null;
        }

        $normalizado = str_replace(' ', '', mb_strtolower(trim($cidade)));

        return $normalizado === '' ? null : hash('sha256', $normalizado);
    }

    public static function country(string $iso2 = 'br'): string
    {
        return hash('sha256', strtolower($iso2));
    }

    public static function externalId(int|string $id): string
    {
        return hash('sha256', (string) $id);
    }

    /**
     * @return array{0: string, 1: string|null}
     */
    private static function dividirNome(string $nome): array
    {
        $partes = preg_split('/\s+/', trim($nome), 2) ?: [];

        return [$partes[0] ?? '', $partes[1] ?? null];
    }

    /**
     * @return list<string>|null
     */
    private static function comoArray(?string $hash): ?array
    {
        return $hash === null ? null : [$hash];
    }
}
