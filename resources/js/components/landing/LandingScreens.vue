<script setup lang="ts">
import { computed, ref } from 'vue';
import { abas, telas } from '@/data/landing';
import type { AbaKey } from '@/data/landing';

const aba = ref<AbaKey>('funil');
const telaAtual = computed(() => telas[aba.value]);
</script>

<template>
    <section class="mx-auto max-w-[1180px] px-7 py-[60px]">
        <div
            class="grid overflow-hidden rounded-2xl bg-[#14181E] px-[28px] pt-[46px] sm:px-[46px] lg:grid-cols-[0.86fr_1.14fr] lg:items-start lg:gap-[44px]"
        >
            <div class="flex flex-col gap-[22px] pb-[46px]">
                <h2
                    class="m-0 font-[family-name:var(--font-landing-display)] text-[28px] leading-[1.16] font-normal tracking-[-0.02em] text-[#FBF9F4] sm:text-[34px]"
                >
                    Três telas que resolvem o dia do time comercial.
                </h2>
                <div class="flex flex-col gap-2">
                    <button
                        v-for="item in abas"
                        :key="item.k"
                        type="button"
                        class="flex cursor-pointer flex-col gap-1 rounded-[10px] border px-4 py-[14px] text-left"
                        :class="
                            aba === item.k
                                ? 'border-[#3A424D] bg-white/7'
                                : 'border-[#23282F] bg-transparent'
                        "
                        @click="aba = item.k"
                    >
                        <span
                            class="text-sm font-medium"
                            :class="
                                aba === item.k
                                    ? 'text-[#FBF9F4]'
                                    : 'text-[#C4CAD3]'
                            "
                        >
                            {{ item.titulo }}
                        </span>
                        <span
                            class="text-[12.5px] leading-normal"
                            :class="
                                aba === item.k
                                    ? 'text-[#A8AFB9]'
                                    : 'text-[#7C8593]'
                            "
                        >
                            {{ item.texto }}
                        </span>
                    </button>
                </div>
            </div>

            <div
                class="self-end overflow-hidden rounded-t-xl border border-b-0 border-[#2B313A] bg-white shadow-[0_-20px_50px_-30px_rgba(0,0,0,0.6)]"
            >
                <div
                    class="flex items-center justify-between border-b border-[#EEEBE4] bg-[#FBFAF7] px-[14px] py-[11px]"
                >
                    <span
                        class="font-[family-name:var(--font-landing-display)] text-sm"
                    >
                        {{ telaAtual.titulo }}
                    </span>
                    <span
                        class="font-[family-name:var(--font-landing-mono)] text-[9.5px] tracking-[0.1em] text-[#9AA2AE] uppercase"
                    >
                        {{ telaAtual.etiqueta }}
                    </span>
                </div>
                <div
                    class="flex min-h-[268px] flex-col gap-2.5 bg-[#F7F5F0] px-4 pt-4 pb-[26px]"
                >
                    <div
                        v-for="linha in telaAtual.linhas"
                        :key="linha.titulo"
                        class="flex items-center justify-between gap-3 rounded-lg border border-[#E3DFD6] bg-white py-[11px] pr-[13px] pl-[13px]"
                        :style="{ borderLeft: `3px solid ${linha.cor}` }"
                    >
                        <span class="flex min-w-0 flex-col gap-[3px]">
                            <span class="text-[12.5px] text-[#171B21]">
                                {{ linha.titulo }}
                            </span>
                            <span class="text-[11px] text-[#77808E]">
                                {{ linha.sub }}
                            </span>
                        </span>
                        <span
                            class="font-[family-name:var(--font-landing-mono)] text-[11px] whitespace-nowrap"
                            :style="{ color: linha.cor }"
                        >
                            {{ linha.valor }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
