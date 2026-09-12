<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CheckCheck, Crown, Goal, Luggage, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { planosDefs } from '@/data/landing';
import { create } from '@/routes/tenants';

const ciclo = ref<'anual' | 'mensual'>('anual');

const planoSlugs: Record<string, string> = {
    Essencial: 'essencial',
    Escritório: 'escritorio',
    Banca: 'banca',
};

const planIcons: Record<string, typeof Goal> = {
    Essencial: Goal,
    Escritório: Crown,
    Banca: Luggage,
};

const planIconColors: Record<string, string> = {
    Essencial: 'text-green-500',
    Escritório: 'text-sky-500',
    Banca: 'text-orange-500',
};

const planos = computed(() =>
    planosDefs.map((plano) => ({
        ...plano,
        preco: ciclo.value === 'anual' ? plano.precoAnual : plano.precoMensal,
        slug: planoSlugs[plano.nome] ?? 'escritorio',
    })),
);

const extrasNegativos = [
    'Disparo em massa',
    'Importação de lista fria',
    'Integração processual',
    'Instância dedicada',
];
</script>

<template>
    <section id="pricing" class="relative pb-32">
        <div class="landing-container">
            <div class="mx-auto text-center xl:max-w-3xl">
                <h2 class="mb-4 capitalize leading-normal">
                    Preço por usuário, sem surpresa
                </h2>
                <p class="text-lg text-slate-500">
                    Implantação, migração de planilhas e treinamento inclusos.
                    Cancelamento a qualquer momento, com exportação completa dos
                    seus dados.
                </p>
                <div
                    class="mt-6 inline-flex gap-0.5 rounded-md border border-slate-200 bg-white p-1"
                >
                    <button
                        type="button"
                        class="rounded px-4 py-1.5 text-sm"
                        :class="
                            ciclo === 'anual'
                                ? 'bg-custom-500 text-white'
                                : 'text-slate-500'
                        "
                        @click="ciclo = 'anual'"
                    >
                        Anual
                    </button>
                    <button
                        type="button"
                        class="rounded px-4 py-1.5 text-sm"
                        :class="
                            ciclo === 'mensual'
                                ? 'bg-custom-500 text-white'
                                : 'text-slate-500'
                        "
                        @click="ciclo = 'mensual'"
                    >
                        Mensal
                    </button>
                </div>
            </div>

            <div
                class="mt-16 grid grid-cols-1 gap-x-5 md:grid-cols-2 xl:grid-cols-3"
            >
                <div
                    v-for="plano in planos"
                    :key="plano.nome"
                    class="landing-card relative !shadow-lg text-15"
                >
                    <div
                        v-if="plano.destaque"
                        class="absolute top-0 size-16 ltr:right-0 rtl:left-0"
                    >
                        <div
                            class="absolute top-6 w-[170px] bg-sky-500 py-1 text-center text-sm font-medium text-white ltr:-right-12 ltr:rotate-45 rtl:-left-12 rtl:-rotate-45"
                        >
                            Mais escolhido
                        </div>
                    </div>
                    <div class="landing-card-body">
                        <h5 class="mb-2 flex items-center">
                            <component
                                :is="planIcons[plano.nome] ?? Goal"
                                class="mr-1 inline-block size-5"
                                :class="planIconColors[plano.nome]"
                            />
                            {{ plano.nome }}
                        </h5>
                        <p class="mb-4 text-slate-500">
                            {{ plano.resumo }}
                        </p>
                        <h3 class="mb-4 font-normal">
                            <span
                                v-if="!plano.sobConsulta"
                                class="text-slate-400"
                            >
                                R$
                            </span>
                            {{ plano.preco.replace('R$ ', '') }}
                            <small
                                v-if="!plano.sobConsulta"
                                class="text-15 text-slate-500"
                            >
                                /usuário · mês
                            </small>
                        </h3>
                        <p class="mb-4 text-sm text-slate-400">
                            {{ plano.minimo }}
                        </p>
                        <Link
                            v-if="plano.ctaType === 'self-service'"
                            :href="create({ query: { plan: plano.slug } })"
                            class="landing-btn landing-btn-dashed"
                        >
                            {{ plano.acao }}
                        </Link>
                        <a
                            v-else
                            href="#contact"
                            class="landing-btn landing-btn-dashed"
                        >
                            {{ plano.acao }}
                        </a>
                        <ul class="mt-5 flex flex-col gap-3">
                            <li
                                v-for="item in plano.itens"
                                :key="item"
                                class="flex items-center gap-2"
                            >
                                <CheckCheck
                                    class="size-4 fill-green-100 text-green-500"
                                />
                                <span>{{ item }}</span>
                            </li>
                            <li
                                v-for="extra in extrasNegativos.slice(
                                    0,
                                    plano.nome === 'Essencial' ? 3 : plano.nome === 'Escritório' ? 1 : 0,
                                )"
                                :key="extra"
                                class="flex items-center gap-2 text-slate-500 line-through"
                            >
                                <X class="size-4 text-red-500" />
                                <span>{{ extra }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
