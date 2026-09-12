<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Rocket } from '@lucide/vue';
import LandingFunnelMock from '@/components/landing/LandingFunnelMock.vue';
import { BRAND } from '@/lib/brand';
import { create } from '@/routes/tenants';
</script>

<template>
    <section id="home" class="relative overflow-hidden pb-24 pt-36 md:pb-32 md:pt-40">
        <div
            class="pointer-events-none absolute -bottom-[250px] hidden size-[500px] rotate-45 rounded-full border border-b-slate-700 border-l-slate-300 border-r-slate-700 border-t-slate-300 border-dashed ltr:right-40 lg:block"
        />
        <div
            class="pointer-events-none absolute -bottom-[350px] hidden size-[700px] rotate-45 rounded-full border border-b-slate-700 border-l-slate-300 border-r-slate-700 border-t-slate-300 border-dashed ltr:right-16 2xl:block"
        />

        <div class="landing-container relative z-10">
            <div
                class="grid items-center gap-10 lg:grid-cols-[minmax(0,0.92fr)_minmax(0,1.08fr)] lg:gap-12"
            >
                <div class="max-w-xl">
                    <p
                        class="mb-4 font-[family-name:var(--font-landing-mono)] text-[11px] tracking-[0.14em] text-slate-500 uppercase"
                    >
                        {{ BRAND.name }} · CRM jurídico
                    </p>
                    <h1 class="mb-6 text-4xl !leading-relaxed md:text-5xl">
                        CRM jurídico para o que acontece
                        <span class="landing-highlight">
                            <span class="landing-highlight-text">antes</span>
                        </span>
                        do processo
                    </h1>
                    <p class="mb-8 text-lg text-slate-500">
                        {{ BRAND.name }} centraliza lead, qualificação, proposta e
                        contrato — com funis por área de atuação, trilha de
                        auditoria e regras de compliance da OAB desde a
                        arquitetura.
                    </p>
                    <div class="flex flex-wrap items-center gap-2">
                        <Link
                            :href="create()"
                            class="landing-btn landing-btn-primary"
                        >
                            <Rocket class="mr-1 inline-block size-4" />
                            Criar conta grátis
                        </Link>
                        <a
                            href="#contact"
                            class="landing-btn border border-dashed border-red-500 bg-white text-red-500 hover:bg-red-50"
                        >
                            Agendar demonstração
                        </a>
                    </div>
                </div>

                <div class="relative min-h-[320px] lg:min-h-[420px]">
                    <div
                        class="landing-hero-shot absolute top-0 right-0 z-0 hidden w-[78%] overflow-hidden rounded-xl border border-slate-200 shadow-xl md:block"
                    >
                        <img
                            src="/images/landing/funil-real.png"
                            alt="Funil LexStart com negociações por etapa"
                            class="block h-auto w-full object-cover object-left-top"
                            width="1280"
                            height="720"
                            loading="eager"
                        />
                    </div>

                    <div
                        class="landing-hero-float relative z-10 mx-auto w-full max-w-[560px] md:absolute md:top-16 md:-left-2 md:mx-0 md:w-[86%] lg:top-20"
                    >
                        <LandingFunnelMock />
                    </div>

                    <div
                        class="landing-hero-chip absolute -bottom-3 right-4 z-20 hidden rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-lg sm:block md:right-8"
                    >
                        <p class="text-[10px] tracking-wide text-slate-400 uppercase">
                            Urgência no card
                        </p>
                        <p class="text-[12.5px] font-medium text-slate-800">
                            Previsão hoje · fundo vermelho claro
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.landing-hero-shot {
    animation: landing-hero-drift 9s ease-in-out infinite;
}

.landing-hero-float {
    animation: landing-hero-rise 0.7s ease-out both;
}

.landing-hero-chip {
    animation: landing-hero-chip 0.6s ease-out 0.45s both;
}

@keyframes landing-hero-drift {
    0%,
    100% {
        transform: translateY(0) rotate(-1.2deg);
    }
    50% {
        transform: translateY(-10px) rotate(-0.4deg);
    }
}

@keyframes landing-hero-rise {
    from {
        opacity: 0;
        transform: translateY(18px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes landing-hero-chip {
    from {
        opacity: 0;
        transform: translateY(8px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .landing-hero-shot,
    .landing-hero-float,
    .landing-hero-chip {
        animation: none;
    }
}
</style>
