<?php

declare(strict_types=1);

namespace App\Support\LeadsEmail\Exceptions;

use RuntimeException;

/**
 * Falha permanente de parsing: o HTML do e-mail não bate com o formato
 * esperado. O Job (`ProcessarLeadEmailRecebido`) trata isso como erro sem
 * retry — tentar de novo não vai mudar o conteúdo do e-mail.
 */
final class LeadEmailParseException extends RuntimeException
{
    public static function tabelaNaoEncontrada(): self
    {
        return new self('Nenhuma tabela de lead encontrada no HTML do e-mail.');
    }

    public static function campoAusente(string $campo): self
    {
        return new self(sprintf('Campo obrigatório "%s" não encontrado na tabela do e-mail.', $campo));
    }

    public static function valorInvalido(string $campo, string $valorBruto): self
    {
        return new self(sprintf('Valor inválido para o campo "%s": "%s".', $campo, $valorBruto));
    }
}
