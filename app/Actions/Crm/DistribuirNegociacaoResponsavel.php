<?php

namespace App\Actions\Crm;

use App\Enums\EtapaFunilResultado;
use App\Models\Empresa;
use App\Models\Funil;
use App\Models\Negociacao;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DistribuirNegociacaoResponsavel
{
    /**
     * Pick the next responsible user according to the funnel distribution rule.
     *
     * Gates: users marked absent are excluded; pending conflict of interest
     * blocks automatic assignment (manual override remains possible upstream).
     */
    public function __invoke(Funil $funil, ?Empresa $empresa = null): User
    {
        if ($this->conflitoPendente($empresa)) {
            throw ValidationException::withMessages([
                'empresa_id' => 'Conflito de interesses pendente. Verifique o conflito ou atribua o responsável manualmente.',
            ]);
        }

        $candidatos = $this->candidatosDisponiveis();

        if ($candidatos->isEmpty()) {
            throw ValidationException::withMessages([
                'responsavel_user_id' => 'Nenhum responsável disponível para distribuição (todos ausentes ou sem usuários no tenant).',
            ]);
        }

        $escolhido = $this->escolher($funil, $candidatos, $empresa);

        $funil->update([
            'ultimo_responsavel_user_id' => $escolhido->id,
        ]);

        return $escolhido;
    }

    /**
     * Re-assign open negotiations in the funnel using the current rule.
     *
     * Deals on the last stage and deals with pending conflict are skipped.
     *
     * @return int Number of negotiations whose responsible changed
     */
    public function redistribuirAbertas(Funil $funil): int
    {
        return (int) DB::transaction(function () use ($funil): int {
            $funil->refresh();
            $funil->load('etapas:id,funil_id,ordem,resultado');

            $abertas = Negociacao::query()
                ->with(['empresa.statusConflito:id,slug', 'empresa.setor:id,slug', 'etapaFunil:id,funil_id,ordem,resultado'])
                ->where('funil_id', $funil->id)
                ->whereHas(
                    'etapaFunil',
                    fn ($etapa) => $etapa->where('resultado', EtapaFunilResultado::Aberta),
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $candidatos = $this->candidatosDisponiveis();

            if ($candidatos->isEmpty() || $abertas->isEmpty()) {
                return 0;
            }

            $funil->update(['ultimo_responsavel_user_id' => null]);
            $funil->refresh();

            $cargas = $candidatos->mapWithKeys(fn (User $user): array => [$user->id => 0])->all();
            $alteradas = 0;

            foreach ($abertas as $negociacao) {
                if ($this->conflitoPendente($negociacao->empresa)) {
                    continue;
                }

                $escolhido = match ($funil->distribuicao) {
                    Funil::DISTRIBUICAO_CARGA => $this->escolherPorCargaEmMemoria($candidatos, $cargas),
                    Funil::DISTRIBUICAO_ESPECIALIDADE => $this->porEspecialidade($funil, $candidatos, $negociacao->empresa),
                    default => $this->proximoDoRoundRobin($funil, $candidatos),
                };

                $funil->ultimo_responsavel_user_id = $escolhido->id;
                $cargas[$escolhido->id] = ($cargas[$escolhido->id] ?? 0) + 1;

                if ($negociacao->responsavel_user_id !== $escolhido->id) {
                    $negociacao->update(['responsavel_user_id' => $escolhido->id]);
                    $alteradas++;
                }
            }

            $funil->save();

            return $alteradas;
        });
    }

    private function conflitoPendente(?Empresa $empresa): bool
    {
        if ($empresa === null) {
            return false;
        }

        $empresa->loadMissing('statusConflito:id,slug');

        return $empresa->statusConflito?->slug === 'pendente';
    }

    /**
     * @return Collection<int, User>
     */
    private function candidatosDisponiveis(): Collection
    {
        return User::query()
            ->whereNotNull('especialidades')
            ->where(function ($query): void {
                $query->whereNull('ausente_ate')
                    ->orWhereDate('ausente_ate', '<', now()->toDateString());
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, User>  $candidatos
     */
    private function escolher(Funil $funil, Collection $candidatos, ?Empresa $empresa): User
    {
        return match ($funil->distribuicao) {
            Funil::DISTRIBUICAO_ESPECIALIDADE => $this->porEspecialidade($funil, $candidatos, $empresa),
            Funil::DISTRIBUICAO_CARGA => $this->porCargaDeTrabalho($funil, $candidatos),
            default => $this->proximoDoRoundRobin($funil, $candidatos),
        };
    }

    /**
     * @param  Collection<int, User>  $candidatos
     * @param  array<int, int>  $cargas
     */
    private function escolherPorCargaEmMemoria(Collection $candidatos, array $cargas): User
    {
        return $candidatos
            ->sortBy(fn (User $user): string => sprintf(
                '%010d-%010d',
                $cargas[$user->id] ?? 0,
                $user->id,
            ))
            ->values()
            ->firstOrFail();
    }

    /**
     * @param  Collection<int, User>  $candidatos
     */
    private function porEspecialidade(Funil $funil, Collection $candidatos, ?Empresa $empresa): User
    {
        $setorSlug = $empresa?->loadMissing('setor:id,slug')->setor?->slug;

        $especialistas = $setorSlug === null
            ? collect()
            : $candidatos->filter(function (User $user) use ($setorSlug): bool {
                $especialidades = $user->especialidades ?? [];

                return in_array($setorSlug, $especialidades, true);
            })->values();

        $pool = $especialistas->isNotEmpty() ? $especialistas : $candidatos;

        return $this->proximoDoRoundRobin($funil, $pool);
    }

    /**
     * @param  Collection<int, User>  $candidatos
     */
    private function porCargaDeTrabalho(Funil $funil, Collection $candidatos): User
    {
        $contagens = Negociacao::query()
            ->where('funil_id', $funil->id)
            ->whereIn('responsavel_user_id', $candidatos->pluck('id'))
            ->selectRaw('responsavel_user_id, count(*) as total')
            ->groupBy('responsavel_user_id')
            ->pluck('total', 'responsavel_user_id');

        return $candidatos
            ->sortBy(fn (User $user): string => sprintf(
                '%010d-%010d',
                (int) ($contagens[$user->id] ?? 0),
                $user->id,
            ))
            ->values()
            ->firstOrFail();
    }

    /**
     * @param  Collection<int, User>  $candidatos
     */
    private function proximoDoRoundRobin(Funil $funil, Collection $candidatos): User
    {
        $ids = $candidatos->pluck('id')->values();
        $ultimoId = $funil->ultimo_responsavel_user_id;
        $indiceUltimo = $ultimoId === null ? -1 : $ids->search($ultimoId);

        if ($indiceUltimo === false) {
            $indiceUltimo = -1;
        }

        $proximoIndice = ($indiceUltimo + 1) % $ids->count();

        return $candidatos->firstWhere('id', $ids[$proximoIndice])
            ?? $candidatos->firstOrFail();
    }
}
