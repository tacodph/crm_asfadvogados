<script setup lang="ts">
import { SCREEN_TITLES, type ScreenKey } from '@/data/crm';

const { screen, periodo = '30 dias' } = defineProps<{
    screen: ScreenKey;
    periodo?: string;
}>();

const titulo = SCREEN_TITLES[screen][0];
const subtitulo = SCREEN_TITLES[screen][1].replace('{periodo}', periodo);
</script>

<template>
    <header
        class="flex h-[60px] shrink-0 items-center justify-between border-b border-[#E7E3DA] bg-white px-[26px]"
    >
        <div class="flex items-baseline gap-3">
            <h1
                class="m-0 font-[family-name:var(--font-crm-display)] text-[22px] font-medium tracking-[-0.01em]"
            >
                {{ titulo }}
            </h1>
            <span class="text-[12.5px] text-[#77808E]">{{ subtitulo }}</span>
        </div>
        <div class="flex items-center gap-2.5">
            <div
                class="flex items-center gap-[7px] rounded-full border border-[#E2D3B6] bg-[#F1E9DA] px-[11px] py-[5px]"
            >
                <span class="h-1.5 w-1.5 rounded-full bg-[#14574F]" />
                <span class="text-[11.5px] font-medium text-[#6F5730]">
                    Modo compliance OAB ativo
                </span>
            </div>
        </div>
    </header>
</template>
