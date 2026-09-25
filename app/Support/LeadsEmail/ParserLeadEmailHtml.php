<?php

declare(strict_types=1);

namespace App\Support\LeadsEmail;

use App\Support\LeadsEmail\Exceptions\LeadEmailParseException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * Extrai os dados de um lead da tabela HTML fixa do e-mail. Casa cada linha
 * pelo texto do rótulo (normalizado: minúsculo, sem acento, sem espaços
 * extras), não pela posição — ferramentas de e-mail marketing às vezes
 * inserem linhas em branco/espaçadoras no meio da tabela.
 */
final class ParserLeadEmailHtml
{
    /** Rótulo normalizado => rótulo de exibição (usado nas mensagens de erro). */
    private const CAMPOS_OBRIGATORIOS = [
        'data do cadastro' => 'Data do Cadastro',
        'nome' => 'Nome',
        'telefone' => 'Telefone',
        'e-mail' => 'E-mail',
        'valor da divida' => 'Valor da Dívida',
        'pf ou pj?' => 'PF ou PJ?',
        'campanha' => 'Campanha',
        'conjunto' => 'Conjunto',
        'anuncio' => 'Anúncio',
    ];

    public function parse(string $html): LeadEmailDados
    {
        $crawler = new Crawler($html);
        $linhas = $crawler->filter('table tr');

        if ($linhas->count() === 0) {
            throw LeadEmailParseException::tabelaNaoEncontrada();
        }

        $campos = $this->extrairCampos($linhas);

        foreach (self::CAMPOS_OBRIGATORIOS as $campo => $rotuloExibicao) {
            if (! array_key_exists($campo, $campos) || $campos[$campo] === '') {
                throw LeadEmailParseException::campoAusente($rotuloExibicao);
            }
        }

        return new LeadEmailDados(
            dataCadastro: $this->parseData($campos['data do cadastro']),
            nome: $campos['nome'],
            telefone: $this->normalizarTelefone($campos['telefone']),
            email: mb_strtolower(trim($campos['e-mail'])),
            valorDivida: $this->parseValorMonetario($campos['valor da divida']),
            tipoPessoa: $this->parseTipoPessoa($campos['pf ou pj?']),
            campanha: $campos['campanha'],
            conjunto: $campos['conjunto'],
            anuncio: $campos['anuncio'],
        );
    }

    /**
     * @return array<string, string> rótulo normalizado => valor bruto (trim)
     */
    private function extrairCampos(Crawler $linhas): array
    {
        $campos = [];

        $linhas->each(function (Crawler $linha) use (&$campos): void {
            $celulas = $linha->filter('td');

            if ($celulas->count() < 2) {
                return;
            }

            $rotulo = $this->normalizarRotulo($celulas->eq(0)->text(''));
            $valor = trim($celulas->eq(1)->text(''));

            if ($rotulo !== '') {
                $campos[$rotulo] = $valor;
            }
        });

        return $campos;
    }

    private function normalizarRotulo(string $rotulo): string
    {
        $rotulo = str_replace(':', '', trim($rotulo));

        return mb_strtolower(Str::ascii($rotulo));
    }

    private function parseData(string $bruto): CarbonImmutable
    {
        $bruto = trim($bruto);

        // Formato brasileiro (d/m/Y[ H:i]) primeiro — CarbonImmutable::parse()
        // interpreta "21/09/2026" como m/d/Y (inglês) e falha em dias > 12.
        foreach (['d/m/Y H:i', 'd/m/Y H:i:s', 'd/m/Y'] as $formato) {
            $data = CarbonImmutable::createFromFormat($formato, $bruto);

            if ($data !== false) {
                return $data;
            }
        }

        try {
            return CarbonImmutable::parse($bruto);
        } catch (Throwable) {
            throw LeadEmailParseException::valorInvalido('Data do Cadastro', $bruto);
        }
    }

    private function normalizarTelefone(string $bruto): string
    {
        return preg_replace('/\D+/', '', $bruto) ?? '';
    }

    private function parseValorMonetario(string $bruto): float
    {
        // "R$ 12.345,67" -> 12345.67
        $limpo = preg_replace('/[^\d,.]/', '', $bruto) ?? '';
        $limpo = str_replace('.', '', $limpo);
        $limpo = str_replace(',', '.', $limpo);

        if ($limpo === '' || ! is_numeric($limpo)) {
            throw LeadEmailParseException::valorInvalido('Valor da Dívida', $bruto);
        }

        return (float) $limpo;
    }

    private function parseTipoPessoa(string $bruto): string
    {
        $normalizado = mb_strtolower(Str::ascii(trim($bruto)));

        return match (true) {
            str_starts_with($normalizado, 'pf'), str_contains($normalizado, 'fisica') => 'pf',
            str_starts_with($normalizado, 'pj'), str_contains($normalizado, 'juridica') => 'pj',
            default => throw LeadEmailParseException::valorInvalido('PF ou PJ?', $bruto),
        };
    }
}
