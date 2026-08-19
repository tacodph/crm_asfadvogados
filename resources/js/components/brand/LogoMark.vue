<script setup lang="ts">
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
        src="/images/logo-sm.png"
        alt="SM"
        :class="[
            sizeClass,
            withPanel
                ? 'rounded-[14px] bg-white p-[7px]'
                : 'rounded-[11px]',
        ]"
        class="shrink-0 object-contain"
    />
</template>
