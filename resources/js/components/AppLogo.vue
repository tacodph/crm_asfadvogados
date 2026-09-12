<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { BRAND } from '@/lib/brand';
</script>

<template>
    <div class="flex items-center">
        <div
            class="flex aspect-square size-8 items-center justify-center overflow-hidden rounded-md"
        >
            <AppLogoIcon class="size-8" />
        </div>
        <div class="ml-1.5 grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate leading-tight font-semibold">{{
                BRAND.name
            }}</span>
        </div>
    </div>
</template>
