<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, provide, ref } from 'vue';
import CrmFooter from '@/components/crm/CrmFooter.vue';
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

const sincronizarPeriodo = (event: Event): void => {
    const detalhe = (event as CustomEvent<string>).detail;

    if (typeof detalhe === 'string' && detalhe !== '') {
        periodo.value = detalhe;
    }
};

onMounted(() => {
    const propPeriodo = page.props.periodo;

    if (typeof propPeriodo === 'string' && propPeriodo !== '') {
        periodo.value = propPeriodo;
    }

    window.addEventListener('crm:periodo', sincronizarPeriodo);
});

onUnmounted(() => {
    window.removeEventListener('crm:periodo', sincronizarPeriodo);
});
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
            href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        class="crm-app flex h-svh w-full overflow-hidden bg-background font-[family-name:var(--font-crm-body)] text-foreground antialiased"
    >
        <CrmSidebar v-model:colapsado="colapsado" />
        <main class="relative flex min-w-0 flex-1 flex-col bg-background">
            <CrmHeader
                v-model:colapsado="colapsado"
                :screen="screen"
                :periodo="periodo"
            />
            <div
                class="relative min-h-0 flex-1 overflow-auto px-4 pt-5 pb-4 md:px-6"
            >
                <slot />
            </div>
            <CrmFooter />
        </main>
        <div id="crm-drawer-root" class="flex h-full min-h-0 shrink-0" />
    </div>
</template>

<style>
.crm-app {
    --font-crm-body: 'IBM Plex Sans', system-ui, sans-serif;
    --font-crm-display: 'IBM Plex Sans', system-ui, sans-serif;
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
    background: color-mix(in srgb, var(--border) 90%, #94a3b8);
    border-radius: 8px;
    border: 3px solid transparent;
    background-clip: padding-box;
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
