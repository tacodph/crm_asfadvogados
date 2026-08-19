<script setup lang="ts">
import type { DrawerField } from '@/types/crm';

defineProps<{
    campos: DrawerField[];
}>();
</script>

<template>
    <div class="grid grid-cols-2 gap-2.5">
        <div
            v-for="campo in campos"
            :key="campo.label"
            class="flex flex-col gap-[3px] rounded-lg border border-[#EEEBE4] bg-[#FBFAF7] px-3 py-2.5"
        >
            <span
                class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-[#77808E] uppercase"
            >
                {{ campo.label }}
            </span>
            <span class="text-[13px] text-[#171B21]">{{ campo.valor }}</span>
        </div>
    </div>
</template>
