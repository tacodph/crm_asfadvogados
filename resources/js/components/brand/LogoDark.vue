<script setup lang="ts">
import { BRAND } from '@/lib/brand';

withDefaults(
    defineProps<{
        heightClass?: string;
        /** Use the light wordmark (dark text) on light surfaces. */
        onLight?: boolean;
    }>(),
    {
        heightClass: 'h-[30px]',
        onLight: false,
    },
);
</script>

<template>
    <img
        :src="onLight ? BRAND.wordmarkLight : BRAND.wordmarkDark"
        :alt="BRAND.name"
        :class="heightClass"
        class="w-auto object-contain object-left"
    />
</template>
