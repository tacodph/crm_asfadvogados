<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, provide, ref } from 'vue';
import CrmHeader from '@/components/crm/CrmHeader.vue';
import CrmSidebar from '@/components/crm/CrmSidebar.vue';
import { SCREEN_TITLES } from '@/data/crm';
import { PAGE_SCREEN } from '@/lib/crmNav';

const colapsado = ref(false);
const periodo = ref('30 dias');
provide('crmPeriodo', periodo);
const page = usePage();
const screen = computed(
    () => PAGE_SCREEN[page.component] ?? ('painel' as const),
);
const tituloAba = computed(() => SCREEN_TITLES[screen.value][0]);
</script>

<template>
    <Head :title="tituloAba">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin=""
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        class="crm-app flex h-svh w-full overflow-hidden bg-[#F7F5F0] font-[family-name:var(--font-crm-body)] text-[#171B21] antialiased"
    >
        <CrmSidebar v-model:colapsado="colapsado" />
        <main class="flex min-w-0 flex-1 flex-col">
            <CrmHeader :screen="screen" :periodo="periodo" />
            <div class="flex-1 overflow-auto px-[26px] pt-6 pb-10">
                <slot />
            </div>
        </main>
        <div id="crm-drawer-root" class="flex h-full min-h-0 shrink-0" />
    </div>
</template>

<style>
.crm-app {
    --font-crm-body: 'IBM Plex Sans', system-ui, sans-serif;
    --font-crm-display: 'Newsreader', Georgia, serif;
    --font-crm-mono: 'IBM Plex Mono', ui-monospace, monospace;
}

.crm-app a {
    text-decoration: none;
}

.crm-app ::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

.crm-app ::-webkit-scrollbar-thumb {
    background: #dcd7cc;
    border-radius: 8px;
    border: 3px solid #f7f5f0;
}

.crm-app ::-webkit-scrollbar-track {
    background: transparent;
}

@keyframes crm-drawer-in {
    from {
        transform: translateX(24px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.crm-drawer {
    animation: crm-drawer-in 180ms ease-out;
}
</style>
