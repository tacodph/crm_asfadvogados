# Prompt 02 — Parser HTML com Symfony DomCrawler

## Objetivo

Transformar o HTML bruto do corpo do e-mail (recebido pelo Job do prompt 03) num DTO
tipado, tratando com segurança HTML corrompido ou campo faltante.

Pré-requisito: prompt 01 executado (`symfony/dom-crawler`/`css-selector` instalados).

## Formato de entrada esperado

Tabela HTML com uma linha `<tr>` por campo, nesta ordem fixa, célula de rótulo + célula de
valor (`<tr><td>Rótulo</td><td>Valor</td></tr>`):

1. Data do Cadastro
2. Nome
3. Telefone
4. E-mail
5. Valor da Dívida
6. PF ou PJ?
7. Campanha
8. Conjunto
9. Anúncio

Não confiar apenas na **posição** da linha (ferramentas de e-mail marketing às vezes
inserem linhas em branco/espaçadoras) — casar cada linha pelo **texto do rótulo**
(normalizado: minúsculo, sem acento, sem espaços extras) é mais robusto e é o que o parser
abaixo faz. A ordem do enunciado vira apenas a lista de rótulos esperados.

## DTO — `App\Support\LeadsEmail\LeadEmailDados`

```php
<?php

declare(strict_types=1);

namespace App\Support\LeadsEmail;

use Carbon\CarbonImmutable;

final readonly class LeadEmailDados
{
    public function __construct(
        public CarbonImmutable $dataCadastro,
        public string $nome,
        public string $telefone,
        public string $email,
        public float $valorDivida,
        public string $tipoPessoa, // 'pf' | 'pj'
        public string $campanha,
        public string $conjunto,
        public string $anuncio,
    ) {
    }
}
```

## Exceção — `App\Support\LeadsEmail\Exceptions\LeadEmailParseException`

```php
<?php

declare(strict_types=1);

namespace App\Support\LeadsEmail\Exceptions;

use RuntimeException;

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
```

Esta exceção sinaliza **falha permanente** (HTML fora do formato esperado) — o Job
(prompt 03) não deve dar retry nela, só registrar erro. É diferente de uma falha de
IMAP/rede, que é transitória e deve ter retry.

## Parser — `App\Support\LeadsEmail\ParserLeadEmailHtml`

```php
<?php

declare(strict_types=1);

namespace App\Support\LeadsEmail;

use App\Support\LeadsEmail\Exceptions\LeadEmailParseException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;

final class ParserLeadEmailHtml
{
    private const CAMPOS_OBRIGATORIOS = [
        'data do cadastro',
        'nome',
        'telefone',
        'e-mail',
        'valor da divida',
        'pf ou pj?',
        'campanha',
        'conjunto',
        'anuncio',
    ];

    public function parse(string $html): LeadEmailDados
    {
        $crawler = new Crawler($html);
        $linhas = $crawler->filter('table tr');

        if ($linhas->count() === 0) {
            throw LeadEmailParseException::tabelaNaoEncontrada();
        }

        $campos = $this->extrairCampos($linhas);

        foreach (self::CAMPOS_OBRIGATORIOS as $campo) {
            if (! array_key_exists($campo, $campos) || $campos[$campo] === '') {
                throw LeadEmailParseException::campoAusente($campo);
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

    /** @return array<string, string> rótulo normalizado => valor bruto (trim) */
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
        // Str::ascii (não iconv//TRANSLIT): a tabela de transliteração do
        // iconv é dependente de locale/glibc e produz resultado diferente
        // por máquina — Str::ascii usa a tabela própria do Laravel, estável.
        $rotulo = str_replace(':', '', trim($rotulo));

        return mb_strtolower(Str::ascii($rotulo));
    }

    private function parseData(string $bruto): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($bruto);
        } catch (\Throwable) {
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
            str_starts_with($normalizado, 'pf') => 'pf',
            str_starts_with($normalizado, 'pj') => 'pj',
            default => throw LeadEmailParseException::valorInvalido('PF ou PJ?', $bruto),
        };
    }
}
```

Observações de implementação:
- `normalizarRotulo` remove acentos via `iconv(...TRANSLIT)` para casar "E-mail"/"Email",
  "Dívida"/"Divida" independente de como o remetente escreveu.
- `parseValorMonetario` assume formato brasileiro (`.` milhar, `,` decimal) — é o formato
  usado nos e-mails de exemplo do domínio jurídico/cobrança do CRM.
- Erros de negócio (campo ausente, valor não parseável) sempre viram
  `LeadEmailParseException` — nunca deixar um `TypeError`/`Throwable` genérico escapar do
  parser, porque o Job decide retry vs falha permanente com base nesse tipo.

## Testes unitários (`tests/Unit/Support/LeadsEmail/ParserLeadEmailHtmlTest.php`)

Casos obrigatórios:
1. HTML válido com os 9 campos na ordem do enunciado → `LeadEmailDados` com todos os campos
   corretos (incluir um caso com `PF` e outro com `PJ`).
2. HTML válido mas com linhas em ordem diferente / linha em branco extra no meio → ainda
   funciona (prova que o casamento é por rótulo, não por posição).
3. HTML sem nenhuma tabela → `LeadEmailParseException::tabelaNaoEncontrada()`.
4. HTML com tabela mas faltando "Valor da Dívida" → `LeadEmailParseException` com mensagem
   citando o campo.
5. "Valor da Dívida" com texto não numérico (ex. "a combinar") → `LeadEmailParseException`.
6. "PF ou PJ?" com valor inesperado (ex. "Pessoa Física") → cobrir o caso de aceitar
   variações comuns ("Pessoa Física"/"Pessoa Jurídica") se o e-mail real usar isso; ajustar
   `parseTipoPessoa` com fixture real antes de fechar este prompt.

Usar fixtures HTML como arquivos em `tests/Fixtures/leads_email/*.html` (não inline gigante
no teste) para o caso válido e os casos corrompidos.

## Critério de aceite

- Todos os testes acima verdes.
- `declare(strict_types=1)` em todos os arquivos novos.
- Nenhum novo erro no phpstan (baseline atual do repo já documentado nas séries
  anteriores — rodar `vendor/bin/phpstan analyse -d memory_limit=1G` e comparar contagem).
