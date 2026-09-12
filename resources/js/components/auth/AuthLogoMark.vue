<script setup lang="ts">
import { BRAND } from '@/lib/brand';

withDefaults(
    defineProps<{
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);
</script>

<template>
    <div class="flex items-center gap-3">
        <img
            :src="BRAND.mark"
            :alt="BRAND.name"
            class="shrink-0 rounded-full object-contain"
            :class="compact ? 'h-[34px] w-[34px]' : 'h-11 w-11'"
        />
        <span v-if="!compact" class="flex flex-col leading-[1.25]">
            <span
                class="font-[family-name:var(--font-auth-display)] text-[17px] font-semibold tracking-[0.02em] text-white"
            >
                {{ BRAND.name }}
            </span>
            <span
                class="font-[family-name:var(--font-auth-mono)] text-[9.5px] tracking-[0.14em] text-slate-400 uppercase"
            >
                {{ BRAND.tagline }}
            </span>
        </span>
    </div>
</template>
