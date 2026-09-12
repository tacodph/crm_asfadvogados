<script setup lang="ts">
import { ArrowRight } from '@lucide/vue';
import { recursos, segmentos, travas } from '@/data/landing';
import { BRAND } from '@/lib/brand';
</script>

<template>
    <section id="about" class="relative py-32">
        <div class="landing-container">
            <div class="mx-auto text-center xl:max-w-3xl">
                <h2 class="mb-6 capitalize leading-normal">
                    Por que escritórios escolhem o
                    <span class="landing-highlight">
                        <span class="landing-highlight-text">{{ BRAND.name }}</span>
                    </span>
                </h2>
                <p class="text-lg text-slate-500">
                    Consultivo empresarial e volume de pessoa física não cabem no
                    mesmo processo comercial. Cada funil tem etapas, campos
                    obrigatórios, SLA e motivos de perda — configurados pelo
                    próprio escritório.
                </p>
            </div>

            <div
                class="mt-10 flex flex-wrap justify-center gap-2"
            >
                <span
                    v-for="segmento in segmentos"
                    :key="segmento"
                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-sm text-slate-600"
                >
                    {{ segmento }}
                </span>
            </div>

            <div
                class="mt-20 grid grid-cols-1 items-center gap-6 lg:grid-cols-12"
            >
                <div class="lg:col-span-5">
                    <h3 class="mb-3 capitalize leading-normal">
                        Funis que falam a língua do escritório
                    </h3>
                    <p class="mb-6 text-lg text-slate-500">
                        {{ recursos[0].texto }}
                    </p>
                    <a href="#pricing" class="landing-btn landing-btn-outline">
                        Ver planos
                        <ArrowRight class="ml-1 inline-block size-4" />
                    </a>
                </div>
                <div class="relative text-center lg:col-span-6 lg:col-start-7">
                    <img
                        src="/images/landing/funil-hero.png"
                        alt="Funil LexStart"
                        class="relative z-10 mx-auto w-full max-w-lg rounded-xl border border-slate-200 shadow-xl"
                        loading="lazy"
                    />
                    <img
                        src="/images/landing/funil-float.png"
                        alt="Detalhe dos cards do funil"
                        class="landing-about-float relative z-20 mx-auto -mt-16 w-[72%] max-w-sm rounded-xl border border-slate-200 shadow-2xl ltr:ml-auto ltr:mr-4 rtl:mr-auto rtl:ml-4"
                        loading="lazy"
                    />
                </div>
            </div>

            <div
                class="mt-32 grid grid-cols-1 items-center gap-6 lg:grid-cols-12"
            >
                <div class="relative text-center lg:col-span-6">
                    <img
                        src="/images/landing/funil-real.png"
                        alt="Visão completa do funil"
                        class="relative z-10 mx-auto w-full max-w-lg rounded-xl border border-slate-200 shadow-xl"
                        loading="lazy"
                    />
                    <img
                        src="/images/landing/funil-float.png"
                        alt="Cards com status e previsão"
                        class="landing-about-float relative z-20 mx-auto -mt-16 w-[70%] max-w-sm rounded-xl border border-slate-200 shadow-2xl ltr:mr-auto ltr:ml-4 rtl:ml-auto rtl:mr-4"
                        loading="lazy"
                    />
                </div>
                <div class="lg:col-span-5 lg:col-start-8">
                    <h3 class="mb-3 capitalize leading-normal">
                        Propostas versionadas e distribuição automática
                    </h3>
                    <p class="mb-6 text-lg text-slate-500">
                        {{ recursos[3].texto }}
                        {{ recursos[4].texto }}
                    </p>
                    <a href="#features" class="landing-btn landing-btn-outline">
                        Ver recursos
                        <ArrowRight class="ml-1 inline-block size-4" />
                    </a>
                </div>
            </div>

            <div
                class="mt-32 grid grid-cols-1 items-center gap-5 lg:grid-cols-12"
            >
                <div class="lg:col-span-5">
                    <h3 class="mb-3 capitalize leading-normal">
                        Compliance por arquitetura, não por aviso
                    </h3>
                    <p class="mb-4 text-lg text-slate-500">
                        Ferramentas de vendas nasceram para disparo em massa e
                        gatilho de urgência — tudo o que a advocacia não pode
                        fazer. O {{ BRAND.name }} inverte a lógica: o que é
                        vedado simplesmente não existe no produto.
                    </p>
                    <ul class="mb-6 flex list-inside list-disc flex-col gap-3 text-lg">
                        <li v-for="trava in travas" :key="trava.titulo">
                            {{ trava.titulo }}
                        </li>
                    </ul>
                    <a href="#contact" class="landing-btn landing-btn-outline">
                        Falar com o time
                        <ArrowRight class="ml-1 inline-block size-4" />
                    </a>
                </div>
                <div class="relative text-center lg:col-span-6 lg:col-start-7">
                    <img
                        src="/images/landing/funil-hero.png"
                        alt="Funil com compliance e status"
                        class="relative z-10 mx-auto w-full max-w-lg rounded-xl border border-slate-200 shadow-xl"
                        loading="lazy"
                    />
                    <img
                        src="/images/landing/funil-real.png"
                        alt="Board completo LexStart"
                        class="landing-about-float relative z-20 mx-auto -mt-16 w-[68%] max-w-sm rounded-xl border border-slate-200 shadow-2xl ltr:ml-auto ltr:mr-6 rtl:mr-auto rtl:ml-6"
                        loading="lazy"
                    />
                </div>
            </div>
        </div>
    </section>
</template>

<style scoped>
.landing-about-float {
    animation: landing-about-float 6s ease-in-out infinite;
}

@keyframes landing-about-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-8px);
    }
}

@media (prefers-reduced-motion: reduce) {
    .landing-about-float {
        animation: none;
    }
}
</style>
