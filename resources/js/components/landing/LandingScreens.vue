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
            class="grid overflow-hidden rounded-2xl bg-background px-[28px] pt-[46px] sm:px-[46px] lg:grid-cols-[0.86fr_1.14fr] lg:items-start lg:gap-[44px]"
        >
            <div class="flex flex-col gap-[22px] pb-[46px]">
                <h2
                    class="m-0 font-[family-name:var(--font-landing-display)] text-[28px] leading-[1.16] font-normal tracking-[-0.02em] text-primary-foreground sm:text-[34px]"
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
                                ? 'border-[#3A424D] bg-card/7'
                                : 'border-[#23282F] bg-transparent'
                        "
                        @click="aba = item.k"
                    >
                        <span
                            class="text-sm font-medium"
                            :class="
                                aba === item.k
                                    ? 'text-primary-foreground'
                                    : 'text-[#C4CAD3]'
                            "
                        >
                            {{ item.titulo }}
                        </span>
                        <span
                            class="text-[12.5px] leading-normal"
                            :class="
                                aba === item.k
                                    ? 'text-muted-foreground'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ item.texto }}
                        </span>
                    </button>
                </div>
            </div>

            <div
                class="self-end overflow-hidden rounded-t-xl border border-b-0 border-border bg-card shadow-[0_-20px_50px_-30px_rgba(0,0,0,0.6)]"
            >
                <div
                    class="flex items-center justify-between border-b border-border bg-card px-[14px] py-[11px]"
                >
                    <span
                        class="font-[family-name:var(--font-landing-display)] text-sm"
                    >
                        {{ telaAtual.titulo }}
                    </span>
                    <span
                        class="font-[family-name:var(--font-landing-mono)] text-[9.5px] tracking-[0.1em] text-muted-foreground uppercase"
                    >
                        {{ telaAtual.etiqueta }}
                    </span>
                </div>
                <TransitionGroup
                    tag="div"
                    name="tela-linha"
                    class="relative flex min-h-[268px] flex-col gap-2.5 bg-background px-4 pt-4 pb-[26px]"
                >
                    <div
                        v-for="(linha, indiceLinha) in telaAtual.linhas"
                        :key="telaAtual.titulo + linha.titulo"
                        class="flex items-center justify-between gap-3 rounded-lg border border-border bg-card py-[11px] pr-[13px] pl-[13px]"
                        :style="{
                            borderLeft: `3px solid ${linha.cor}`,
                            transitionDelay: `${indiceLinha * 50}ms`,
                        }"
                    >
                        <span class="flex min-w-0 flex-col gap-[3px]">
                            <span class="text-[12.5px] text-foreground">
                                {{ linha.titulo }}
                            </span>
                            <span class="text-[11px] text-muted-foreground">
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
                </TransitionGroup>
            </div>
        </div>
    </section>
</template>

<style scoped>
.tela-linha-enter-active,
.tela-linha-leave-active {
    transition:
        opacity 0.32s ease,
        transform 0.32s ease;
}

.tela-linha-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.tela-linha-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.tela-linha-leave-active {
    position: absolute;
    right: 16px;
    left: 16px;
}

@media (prefers-reduced-motion: reduce) {
    .tela-linha-enter-active,
    .tela-linha-leave-active {
        transition: none;
    }
}
</style>
