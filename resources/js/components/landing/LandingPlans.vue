<script setup lang="ts">
import { computed, ref } from 'vue';
import { planosDefs } from '@/data/landing';

const ciclo = ref<'anual' | 'mensal'>('anual');

const notaCiclo = computed(() =>
    ciclo.value === 'anual'
        ? 'economia de 2 meses'
        : 'sem compromisso de prazo',
);

const planos = computed(() =>
    planosDefs.map((plano) => {
        const preco =
            ciclo.value === 'anual' ? plano.precoAnual : plano.precoMensal;

        return {
            ...plano,
            preco,
            unidade: plano.sobConsulta ? '' : 'por usuário / mês',
        };
    }),
);
</script>

<template>
    <section id="planos" class="border-y border-[#E7E3DA] bg-[#FBFAF7]">
        <div class="mx-auto flex max-w-[1180px] flex-col gap-8 px-7 py-[68px]">
            <div class="flex flex-wrap items-end justify-between gap-[30px]">
                <div class="flex max-w-[520px] flex-col gap-3">
                    <h2
                        class="m-0 font-[family-name:var(--font-landing-display)] text-[32px] leading-[1.12] font-normal tracking-[-0.02em] sm:text-[40px]"
                    >
                        Preço por usuário, sem surpresa.
                    </h2>
                    <p class="m-0 text-[15px] leading-[1.68] text-[#3C4450]">
                        Implantação, migração de planilhas e treinamento
                        inclusos em todos os planos. Cancelamento a qualquer
                        momento, com exportação completa dos seus dados.
                    </p>
                </div>
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex gap-0.5 rounded-[9px] border border-[#E3DFD6] bg-white p-[3px]"
                    >
                        <button
                            type="button"
                            class="cursor-pointer rounded-[7px] border-0 px-[14px] py-[7px] text-[12.5px]"
                            :class="
                                ciclo === 'anual'
                                    ? 'bg-[#171B21] text-white'
                                    : 'bg-transparent text-[#77808E]'
                            "
                            @click="ciclo = 'anual'"
                        >
                            Anual
                        </button>
                        <button
                            type="button"
                            class="cursor-pointer rounded-[7px] border-0 px-[14px] py-[7px] text-[12.5px]"
                            :class="
                                ciclo === 'mensal'
                                    ? 'bg-[#171B21] text-white'
                                    : 'bg-transparent text-[#77808E]'
                            "
                            @click="ciclo = 'mensal'"
                        >
                            Mensal
                        </button>
                    </div>
                    <span class="text-xs text-[#14574F]">
                        {{ notaCiclo }}
                    </span>
                </div>
            </div>

            <div class="grid items-start gap-4 lg:grid-cols-3">
                <div
                    v-for="plano in planos"
                    :key="plano.nome"
                    class="flex flex-col gap-4 rounded-[13px] border px-6 pt-[26px] pb-7"
                    :class="
                        plano.destaque
                            ? 'border-[#14181E] bg-[#14181E]'
                            : 'border-[#E3DFD6] bg-white'
                    "
                >
                    <div class="flex items-center justify-between gap-2.5">
                        <span
                            class="text-sm font-semibold"
                            :class="
                                plano.destaque
                                    ? 'text-[#FBF9F4]'
                                    : 'text-[#171B21]'
                            "
                        >
                            {{ plano.nome }}
                        </span>
                        <span
                            v-if="plano.destaque"
                            class="inline-flex rounded-full border border-[#E2D3B6] bg-[#F6EFE2] px-[9px] py-[3px] text-[10.5px] text-[#6F5730]"
                        >
                            mais escolhido
                        </span>
                    </div>
                    <div class="flex flex-col gap-1">
                        <span class="flex items-baseline gap-1.5">
                            <span
                                class="font-[family-name:var(--font-landing-display)] text-[38px] leading-none font-medium"
                                :class="
                                    plano.destaque
                                        ? 'text-[#FBF9F4]'
                                        : 'text-[#171B21]'
                                "
                            >
                                {{ plano.preco }}
                            </span>
                            <span
                                v-if="plano.unidade"
                                class="text-[12.5px]"
                                :class="
                                    plano.destaque
                                        ? 'text-[#7C8593]'
                                        : 'text-[#77808E]'
                                "
                            >
                                {{ plano.unidade }}
                            </span>
                        </span>
                        <span
                            class="text-xs"
                            :class="
                                plano.destaque
                                    ? 'text-[#7C8593]'
                                    : 'text-[#77808E]'
                            "
                        >
                            {{ plano.minimo }}
                        </span>
                    </div>
                    <p
                        class="m-0 text-[13px] leading-[1.6]"
                        :class="
                            plano.destaque ? 'text-[#A8AFB9]' : 'text-[#5F6875]'
                        "
                    >
                        {{ plano.resumo }}
                    </p>
                    <div
                        class="flex flex-col gap-2 border-t pt-[14px]"
                        :class="
                            plano.destaque
                                ? 'border-[#2B313A]'
                                : 'border-[#EEEBE4]'
                        "
                    >
                        <span
                            v-for="item in plano.itens"
                            :key="item"
                            class="grid grid-cols-[14px_1fr] items-start gap-[9px] text-[12.5px] leading-normal"
                            :class="
                                plano.destaque
                                    ? 'text-[#A8AFB9]'
                                    : 'text-[#5F6875]'
                            "
                        >
                            <span
                                class="text-[11px]"
                                :class="
                                    plano.destaque
                                        ? 'text-[#C79A4E]'
                                        : 'text-[#14574F]'
                                "
                            >
                                ✓
                            </span>
                            <span>{{ item }}</span>
                        </span>
                    </div>
                    <a
                        href="#demonstracao"
                        class="mt-auto rounded-[9px] border py-3 text-center text-[13.5px] font-medium"
                        :class="
                            plano.destaque
                                ? 'border-[#C79A4E] bg-[#C79A4E] text-[#14181E] hover:bg-[#d4a85c]'
                                : 'border-[#D8D2C5] bg-white text-[#171B21] hover:bg-[#FBFAF7]'
                        "
                    >
                        {{ plano.acao }}
                    </a>
                </div>
            </div>
        </div>
    </section>
</template>
