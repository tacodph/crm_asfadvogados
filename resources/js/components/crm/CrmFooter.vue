<script setup lang="ts">
import { BRAND } from '@/lib/brand';
</script>

<template>
    <footer
        class="flex h-14 shrink-0 items-center border-t border-border bg-card px-4 text-[13px] text-muted-foreground md:px-6"
    >
        <div
            class="flex w-full flex-col items-center justify-between gap-1 sm:flex-row"
        >
            <span>{{ new Date().getFullYear() }} © {{ BRAND.name }}.</span>
            <span class="hidden sm:inline">
                CRM jurídico · dados hospedados no Brasil
            </span>
        </div>
    </footer>
</template>
