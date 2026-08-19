<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div
        v-if="message"
        class="flex items-start gap-2.5 rounded-lg border border-[#E6C9C2] bg-[#FCF5F3] px-3 py-2.5"
    >
        <span
            class="shrink-0 pt-0.5 font-[family-name:var(--font-auth-mono)] text-[9.5px] tracking-[0.1em] text-[#9B3B2F] uppercase"
        >
            Erro
        </span>
        <span class="text-[12.5px] leading-normal text-[#3C4450]">
            {{ message }}
        </span>
    </div>
</template>
