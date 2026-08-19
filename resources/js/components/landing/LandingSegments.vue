<script setup lang="ts">
import { segmentos } from '@/data/landing';
</script>

<template>
    <section class="mx-auto max-w-[1180px] px-7 pt-[30px] pb-2.5">
        <div
            class="flex flex-wrap items-center justify-between gap-6 border-y border-[#E7E3DA] py-5"
        >
            <span
                class="font-[family-name:var(--font-landing-mono)] text-[9.5px] tracking-[0.13em] text-[#A9A296] uppercase"
            >
                Em operação em escritórios de
            </span>
            <div class="flex flex-wrap gap-[34px]">
                <span
                    v-for="segmento in segmentos"
                    :key="segmento"
                    class="font-[family-name:var(--font-landing-display)] text-[15px] text-[#77808E]"
                >
                    {{ segmento }}
                </span>
            </div>
        </div>
    </section>
</template>
