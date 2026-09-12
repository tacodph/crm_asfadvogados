<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { heroColunas } from '@/data/landing';

const visivel = ref(false);

onMounted(() => {
    requestAnimationFrame(() => {
        visivel.value = true;
    });
});
</script>

<template>
    <div
        class="landing-funnel-mock relative overflow-hidden rounded-xl border border-slate-200 bg-[#F7F5F0] shadow-[0_24px_60px_-28px_rgba(23,27,33,0.45)]"
        :class="{ 'is-ready': visivel }"
    >
        <div
            class="flex items-center justify-between border-b border-slate-200/80 bg-white/90 px-3 py-2.5"
        >
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-red-400" />
                <span class="size-2 rounded-full bg-amber-400" />
                <span class="size-2 rounded-full bg-emerald-400" />
                <span class="ml-2 text-[11px] font-medium text-slate-600">
                    LexStart · Funil
                </span>
            </div>
            <span
                class="rounded-full bg-sky-50 px-2 py-0.5 text-[10px] font-medium text-sky-700"
            >
                Concursos (PF)
            </span>
        </div>

        <div
            class="flex gap-2.5 overflow-hidden px-3 py-3"
        >
            <div
                v-for="(coluna, indice) in heroColunas"
                :key="coluna.nome"
                class="landing-funnel-col flex w-[148px] shrink-0 flex-col gap-2"
                :style="{ '--delay': `${indice * 90}ms` }"
            >
                <div
                    class="rounded-md px-2.5 py-2"
                    :style="{ background: coluna.cor }"
                >
                    <div class="flex items-center justify-between gap-1">
                        <span class="truncate text-[10.5px] font-semibold text-white">
                            {{ coluna.nome }}
                        </span>
                        <span
                            class="rounded-full bg-white/15 px-1.5 text-[10px] text-white"
                        >
                            {{ coluna.qtd }}
                        </span>
                    </div>
                </div>

                <div
                    v-for="(card, cardIndice) in coluna.cards"
                    :key="card.nome"
                    class="landing-funnel-card rounded-lg border border-slate-200/90 bg-white p-2.5 shadow-sm"
                    :class="{ 'landing-funnel-card--urgent': card.urgente }"
                    :style="{ '--delay': `${indice * 90 + cardIndice * 70 + 120}ms` }"
                >
                    <p class="truncate text-[11px] font-medium text-slate-800">
                        {{ card.nome }}
                    </p>
                    <p class="mt-0.5 truncate text-[10px] text-slate-500">
                        {{ card.assunto }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <span
                            class="rounded-full px-1.5 py-0.5 text-[9px] font-medium"
                            :style="{
                                color: card.statusCor,
                                background: card.statusBg,
                            }"
                        >
                            {{ card.status }}
                        </span>
                        <span
                            class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[9px] text-slate-500"
                        >
                            {{ card.canal }}
                        </span>
                    </div>
                    <div
                        class="mt-2 flex items-center justify-between gap-1 border-t border-slate-100 pt-1.5 text-[9.5px]"
                    >
                        <span class="truncate text-slate-600">{{ card.responsavel }}</span>
                        <span class="shrink-0 font-medium text-slate-800">
                            {{ card.valor }}
                        </span>
                    </div>
                    <div
                        class="mt-1 flex items-center justify-between text-[9px] text-slate-500"
                    >
                        <span>Previsão</span>
                        <span
                            class="font-medium"
                            :class="card.urgente ? 'text-red-700' : 'text-slate-700'"
                        >
                            {{ card.previsao }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.landing-funnel-col,
.landing-funnel-card {
    opacity: 0;
    transform: translateY(14px);
    transition:
        opacity 0.55s ease,
        transform 0.55s ease;
    transition-delay: var(--delay, 0ms);
}

.landing-funnel-mock.is-ready .landing-funnel-col,
.landing-funnel-mock.is-ready .landing-funnel-card {
    opacity: 1;
    transform: translateY(0);
}

.landing-funnel-card--urgent {
    background: #fdf2f0;
}

.landing-funnel-mock.is-ready .landing-funnel-card {
    animation: landing-card-float 5.5s ease-in-out infinite;
    animation-delay: calc(var(--delay, 0ms) + 700ms);
}

@keyframes landing-card-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-4px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .landing-funnel-col,
    .landing-funnel-card {
        opacity: 1;
        transform: none;
        transition: none;
        animation: none !important;
    }
}
</style>
