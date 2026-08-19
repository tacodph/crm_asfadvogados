<script setup lang="ts">
withDefaults(
    defineProps<{
        heightClass?: string;
    }>(),
    {
        heightClass: 'h-[30px]',
    },
);
</script>

<template>
    <img
        src="/images/logo-dark.png"
        alt="CRM ASF"
        :class="heightClass"
        class="w-auto"
    />
</template>
