<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import { recursos } from '@/data/landing';

const featureCards = [
    {
        badge: 'Funil',
        badgeClass: 'bg-sky-100 border-sky-200 text-sky-600',
        image: '/images/landing/funil-real.png',
        imageAlt: 'Funil LexStart com colunas de negociação',
        titulo: 'Negociações por etapa',
        texto: recursos[0].texto,
    },
    {
        badge: 'Prioridade',
        badgeClass: 'bg-amber-100 border-amber-200 text-amber-700',
        image: '/images/landing/funil-hero.png',
        imageAlt: 'Cards do funil com previsão e status',
        titulo: 'Previsão e urgência no card',
        texto: 'Destaque visual para leads com previsão de fechamento hoje ou nos próximos dias — o time vê o que precisa de atenção sem abrir a ficha.',
    },
    {
        badge: 'Produto',
        badgeClass: 'bg-emerald-100 border-emerald-200 text-emerald-700',
        image: '/images/landing/funil-float.png',
        imageAlt: 'Detalhe do funil LexStart',
        titulo: 'Operação comercial clara',
        texto: recursos[2]?.texto ?? 'Calendário, tarefas e histórico no mesmo fluxo da negociação.',
    },
];

const stats = [
    { value: 5, label: 'Áreas de atuação' },
    { value: 3, label: 'Funis configuráveis' },
    { value: 6, label: 'Módulos principais' },
    { value: 2, label: 'Ciclos de cobrança' },
    { value: 100, suffix: '%', label: 'Dados no Brasil' },
];
</script>

<template>
    <section id="features" class="relative bg-slate-900 py-28 md:py-32">
        <div class="landing-container">
            <div class="grid items-end gap-8 lg:grid-cols-12 lg:gap-10">
                <div class="lg:col-span-5">
                    <p
                        class="mb-3 font-[family-name:var(--font-landing-mono)] text-[11px] tracking-[0.14em] text-slate-400 uppercase"
                    >
                        Produto em ação
                    </p>
                    <h2 class="mb-4 text-3xl leading-snug text-slate-100 md:text-4xl">
                        O funil que o time comercial usa no dia a dia
                    </h2>
                    <p class="text-base text-slate-400">
                        Colunas por etapa, status de atendimento e qualificação,
                        responsável e previsão de fechamento — sem poluir o card.
                    </p>
                </div>
                <div class="lg:col-span-7">
                    <div
                        class="overflow-hidden rounded-xl border border-slate-700 shadow-2xl"
                    >
                        <img
                            src="/images/landing/funil-real.png"
                            alt="Visão geral do funil LexStart"
                            class="block h-auto w-full object-cover object-left-top"
                            width="1280"
                            height="720"
                            loading="lazy"
                        />
                    </div>
                </div>
            </div>

            <div class="mt-20">
                <h2 class="mb-4 capitalize leading-normal text-slate-100">
                    Feito para quem comercializa serviços jurídicos com
                    responsabilidade
                </h2>
            </div>
        </div>

        <div
            class="pointer-events-none absolute -left-80 bottom-48 hidden size-[500px] rotate-45 rounded-full border border-dashed border-slate-700 md:block"
        />
        <div
            class="pointer-events-none absolute -left-96 bottom-24 hidden size-[700px] rotate-45 rounded-full border border-dashed border-slate-700 md:block"
        />
    </section>

    <section class="relative bg-slate-50 pb-28 pt-8 md:pb-32">
        <div class="landing-container">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div
                    v-for="(card, index) in featureCards"
                    :key="card.titulo"
                    class="landing-card transition-all duration-300 ease-linear hover:-translate-y-2"
                    :class="{
                        'lg:-mt-28': index < 3,
                    }"
                >
                    <div class="p-5">
                        <div
                            class="overflow-hidden rounded-lg border border-slate-200 shadow-sm"
                        >
                            <img
                                :src="card.image"
                                :alt="card.imageAlt"
                                class="aspect-[16/10] w-full object-cover object-left-top"
                                loading="lazy"
                            />
                        </div>
                        <div class="mt-5">
                            <span
                                class="inline-block rounded border px-2.5 py-0.5 text-xs font-medium"
                                :class="card.badgeClass"
                            >
                                {{ card.badge }}
                            </span>
                            <h3 class="mt-3 mb-2 text-lg">
                                {{ card.titulo }}
                            </h3>
                            <p class="mb-3 text-16 text-slate-500">
                                {{ card.texto }}
                            </p>
                            <a
                                href="#pricing"
                                class="inline-flex items-center gap-1 text-sm font-medium text-sky-600 hover:text-sky-700"
                            >
                                Ver planos
                                <ArrowRight class="size-4" />
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="mt-16 grid grid-cols-2 gap-6 border-t border-slate-200 pt-10 md:grid-cols-5"
            >
                <div
                    v-for="stat in stats"
                    :key="stat.label"
                    class="text-center"
                >
                    <p
                        class="text-3xl font-semibold text-slate-800"
                        data-target="stat"
                        :data-value="stat.value"
                    >
                        {{ stat.value }}{{ stat.suffix ?? '' }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500">{{ stat.label }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
