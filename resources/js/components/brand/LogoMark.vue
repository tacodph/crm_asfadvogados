<script setup lang="ts">
import { BRAND } from '@/lib/brand';

withDefaults(
    defineProps<{
        sizeClass?: string;
        withPanel?: boolean;
    }>(),
    {
        sizeClass: 'h-[38px] w-[38px]',
        withPanel: false,
    },
);
</script>

<template>
    <img
        :src="BRAND.mark"
        :alt="BRAND.name"
        :class="[
            sizeClass,
            withPanel
                ? 'rounded-[14px] bg-white p-[7px]'
                : 'rounded-[11px]',
        ]"
        class="shrink-0 object-contain"
    />
</template>
