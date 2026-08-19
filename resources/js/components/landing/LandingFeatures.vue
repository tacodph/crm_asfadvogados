<script setup lang="ts">
import { recursos } from '@/data/landing';
</script>

<template>
    <section
        id="produto"
        class="mx-auto flex max-w-[1180px] flex-col gap-[34px] px-7 pt-[68px] pb-3"
    >
        <div
            class="grid items-end gap-8 lg:grid-cols-[0.9fr_1.1fr] lg:gap-[44px]"
        >
            <h2
                class="m-0 font-[family-name:var(--font-landing-display)] text-[32px] leading-[1.12] font-normal tracking-[-0.02em] sm:text-[40px]"
            >
                Um funil que fala a língua do escritório.
            </h2>
            <p class="m-0 text-[15.5px] leading-[1.7] text-[#3C4450]">
                Consultivo empresarial e volume de pessoa física não cabem no
                mesmo processo comercial. No Antessala, cada funil tem suas
                etapas, campos obrigatórios, SLA e motivos de perda —
                configurados pelo próprio escritório, sem desenvolvedor.
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="recurso in recursos"
                :key="recurso.titulo"
                class="flex flex-col gap-[11px] rounded-xl border border-[#E3DFD6] bg-white px-[22px] pt-[22px] pb-6"
            >
                <span
                    class="grid h-[34px] w-[34px] place-items-center rounded-[9px] font-[family-name:var(--font-landing-display)] text-base"
                    :style="{
                        background: recurso.iconeBg,
                        color: recurso.iconeCor,
                    }"
                >
                    {{ recurso.marca }}
                </span>
                <h3 class="m-0 text-[15px] font-semibold tracking-[-0.005em]">
                    {{ recurso.titulo }}
                </h3>
                <p class="m-0 text-[13.5px] leading-[1.62] text-[#5F6875]">
                    {{ recurso.texto }}
                </p>
            </div>
        </div>
    </section>
</template>
