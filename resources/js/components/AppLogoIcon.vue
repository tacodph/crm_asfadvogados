<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { BRAND } from '@/lib/brand';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        :src="BRAND.mark"
        :alt="BRAND.name"
        :class="className"
        class="object-contain"
        v-bind="$attrs"
    />
</template>
