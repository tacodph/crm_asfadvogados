<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\LeadsEmail\Exceptions\LeadEmailParseException;
use App\Support\LeadsEmail\ParserLeadEmailHtml;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParserLeadEmailHtmlTest extends TestCase
{
    use RefreshDatabase;

    private function tabela(array $linhas): string
    {
        $tr = collect($linhas)
            ->map(fn (array $par): string => sprintf('<tr><td>%s</td><td>%s</td></tr>', $par[0], $par[1]))
            ->implode('');

        return "<table>{$tr}</table>";
    }

    private function linhasValidas(array $overrides = []): array
    {
        return array_replace([
            'Data do Cadastro' => '21/09/2026 10:00',
            'Nome' => 'Marina Yoshida',
            'Telefone' => '(11) 98888-7777',
            'E-mail' => 'Marina@Exemplo.com',
            'Valor da Dívida' => 'R$ 12.345,67',
            'PF ou PJ?' => 'PF',
            'Campanha' => 'Bancário 2026',
            'Conjunto' => 'Conjunto A',
            'Anúncio' => 'Anúncio 1',
        ], $overrides);
    }

    public function test_parses_valid_html_in_the_expected_order(): void
    {
        $html = $this->tabela(array_map(
            fn (string $k, string $v): array => [$k, $v],
            array_keys($this->linhasValidas()),
            array_values($this->linhasValidas()),
        ));

        $dados = (new ParserLeadEmailHtml)->parse($html);

        $this->assertSame('Marina Yoshida', $dados->nome);
        $this->assertSame('11988887777', $dados->telefone);
        $this->assertSame('marina@exemplo.com', $dados->email);
        $this->assertSame(12345.67, $dados->valorDivida);
        $this->assertSame('pf', $dados->tipoPessoa);
        $this->assertSame('Bancário 2026', $dados->campanha);
        $this->assertSame('Conjunto A', $dados->conjunto);
        $this->assertSame('Anúncio 1', $dados->anuncio);
        $this->assertSame('2026-09-21', $dados->dataCadastro->toDateString());
    }

    public function test_matches_by_label_not_position(): void
    {
        $linhas = $this->linhasValidas(['PF ou PJ?' => 'Pessoa Jurídica']);

        // Ordem embaralhada + linha em branco extra no meio.
        $html = '<table>'
            .'<tr><td>Campanha</td><td>'.$linhas['Campanha'].'</td></tr>'
            .'<tr><td></td><td></td></tr>'
            .'<tr><td>Nome</td><td>'.$linhas['Nome'].'</td></tr>'
            .'<tr><td>Data do Cadastro</td><td>'.$linhas['Data do Cadastro'].'</td></tr>'
            .'<tr><td>Telefone</td><td>'.$linhas['Telefone'].'</td></tr>'
            .'<tr><td>E-mail</td><td>'.$linhas['E-mail'].'</td></tr>'
            .'<tr><td>Valor da Dívida</td><td>'.$linhas['Valor da Dívida'].'</td></tr>'
            .'<tr><td>PF ou PJ?</td><td>'.$linhas['PF ou PJ?'].'</td></tr>'
            .'<tr><td>Conjunto</td><td>'.$linhas['Conjunto'].'</td></tr>'
            .'<tr><td>Anúncio</td><td>'.$linhas['Anúncio'].'</td></tr>'
            .'</table>';

        $dados = (new ParserLeadEmailHtml)->parse($html);

        $this->assertSame('pj', $dados->tipoPessoa);
        $this->assertSame('Marina Yoshida', $dados->nome);
    }

    public function test_missing_table_throws(): void
    {
        $this->expectException(LeadEmailParseException::class);
        $this->expectExceptionMessage('Nenhuma tabela de lead encontrada');

        (new ParserLeadEmailHtml)->parse('<p>sem tabela nenhuma</p>');
    }

    public function test_missing_required_field_throws(): void
    {
        $linhas = $this->linhasValidas();
        unset($linhas['Valor da Dívida']);

        $html = $this->tabela(array_map(fn (string $k, string $v): array => [$k, $v], array_keys($linhas), array_values($linhas)));

        $this->expectException(LeadEmailParseException::class);
        $this->expectExceptionMessage('Valor da Dívida');

        (new ParserLeadEmailHtml)->parse($html);
    }

    public function test_non_numeric_value_throws(): void
    {
        $linhas = $this->linhasValidas(['Valor da Dívida' => 'a combinar']);

        $html = $this->tabela(array_map(fn (string $k, string $v): array => [$k, $v], array_keys($linhas), array_values($linhas)));

        $this->expectException(LeadEmailParseException::class);

        (new ParserLeadEmailHtml)->parse($html);
    }

    public function test_unexpected_pf_pj_value_throws(): void
    {
        $linhas = $this->linhasValidas(['PF ou PJ?' => 'não sei']);

        $html = $this->tabela(array_map(fn (string $k, string $v): array => [$k, $v], array_keys($linhas), array_values($linhas)));

        $this->expectException(LeadEmailParseException::class);

        (new ParserLeadEmailHtml)->parse($html);
    }
}
