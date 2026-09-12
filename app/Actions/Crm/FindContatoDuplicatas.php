<?php

namespace App\Actions\Crm;

use App\Models\Contato;
use Illuminate\Support\Collection;

class FindContatoDuplicatas
{
    public function __construct(
        private NormalizeContatoIdentifiers $normalize,
    ) {}

    /**
     * Deterministic matches within the current tenant.
     *
     * @return Collection<int, array{
     *     campo: string,
     *     motivo: string,
     *     mensagem: string,
     *     contato: array{id: int, nome: string, email: string|null, telefone: string|null, cpf: string|null}
     * }>
     */
    public function __invoke(
        ?string $email = null,
        ?string $telefone = null,
        ?string $cpf = null,
        ?int $ignoreId = null,
    ): Collection {
        $email = $this->normalize->email($email);
        $telefone = $this->normalize->telefone($telefone);
        $cpf = $this->normalize->cpf($cpf);

        if ($email === null && $telefone === null && $cpf === null) {
            return collect();
        }

        /** @var Collection<int, array{campo: string, motivo: string, mensagem: string, contato: array{id: int, nome: string, email: string|null, telefone: string|null, cpf: string|null}}> $matches */
        $matches = collect();

        if ($email !== null) {
            Contato::query()
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->whereRaw('LOWER(email) = ?', [$email])
                ->orderBy('nome')
                ->limit(20)
                ->get(['id', 'nome', 'email', 'telefone', 'cpf'])
                ->each(function (Contato $contato) use ($matches): void {
                    $matches->push($this->match('email', 'e-mail', $contato));
                });
        }

        if ($cpf !== null) {
            Contato::query()
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->whereNotNull('cpf')
                ->orderBy('nome')
                ->limit(100)
                ->get(['id', 'nome', 'email', 'telefone', 'cpf'])
                ->filter(fn (Contato $contato): bool => $this->normalize->cpf($contato->cpf) === $cpf)
                ->each(function (Contato $contato) use ($matches): void {
                    $matches->push($this->match('cpf', 'CPF', $contato));
                });
        }

        if ($telefone !== null) {
            Contato::query()
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->whereNotNull('telefone')
                ->orderBy('nome')
                ->limit(200)
                ->get(['id', 'nome', 'email', 'telefone', 'cpf'])
                ->filter(fn (Contato $contato): bool => $this->normalize->telefone($contato->telefone) === $telefone)
                ->each(function (Contato $contato) use ($matches): void {
                    $matches->push($this->match('telefone', 'telefone', $contato));
                });
        }

        return $matches
            ->unique(fn (array $item): string => $item['campo'].'-'.$item['contato']['id'])
            ->values();
    }

    /**
     * @return array{
     *     campo: string,
     *     motivo: string,
     *     mensagem: string,
     *     contato: array{id: int, nome: string, email: string|null, telefone: string|null, cpf: string|null}
     * }
     */
    private function match(string $campo, string $motivo, Contato $contato): array
    {
        return [
            'campo' => $campo,
            'motivo' => $motivo,
            'mensagem' => "Já existe o contato «{$contato->nome}» (#{$contato->id}) com este {$motivo}.",
            'contato' => [
                'id' => $contato->id,
                'nome' => $contato->nome,
                'email' => $contato->email,
                'telefone' => $contato->telefone,
                'cpf' => $contato->cpf,
            ],
        ];
    }
}
