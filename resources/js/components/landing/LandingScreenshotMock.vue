<script setup lang="ts">
import type { Tela } from '@/data/landing';

defineProps<{
    tela: Tela;
    variant?: 'dashboard' | 'compact' | 'wide';
}>();
</script>

<template>
    <div
        class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
        :class="variant === 'compact' ? 'text-[10px]' : 'text-xs'"
    >
        <div
            class="flex items-center justify-between border-b border-slate-100 bg-slate-50 px-3 py-2"
        >
            <div class="flex items-center gap-1.5">
                <span class="size-2 rounded-full bg-red-400" />
                <span class="size-2 rounded-full bg-amber-400" />
                <span class="size-2 rounded-full bg-emerald-400" />
            </div>
            <span class="font-medium text-slate-600">{{ tela.titulo }}</span>
            <span
                class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] text-sky-600"
            >
                {{ tela.etiqueta }}
            </span>
        </div>
        <div class="space-y-2 p-3">
            <div
                v-for="linha in tela.linhas.slice(0, variant === 'compact' ? 3 : 5)"
                :key="linha.titulo"
                class="flex items-center justify-between rounded-md border border-slate-100 px-2.5 py-2"
            >
                <div class="min-w-0">
                    <p class="truncate font-medium text-slate-700">
                        {{ linha.titulo }}
                    </p>
                    <p class="truncate text-slate-400">{{ linha.sub }}</p>
                </div>
                <span
                    class="shrink-0 rounded px-1.5 py-0.5 font-medium text-white"
                    :style="{ backgroundColor: linha.cor }"
                >
                    {{ linha.valor }}
                </span>
            </div>
        </div>
    </div>
</template>
