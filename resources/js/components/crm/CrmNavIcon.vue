<script setup lang="ts">
import type { ScreenKey } from '@/data/crm';

const { name } = defineProps<{
    name: ScreenKey;
}>();
</script>

<template>
    <svg
        width="17"
        height="17"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.6"
        stroke-linecap="round"
        stroke-linejoin="round"
        class="shrink-0"
    >
        <template v-if="name === 'painel'">
            <rect x="3" y="3" width="7" height="9" rx="1.5" />
            <rect x="14" y="3" width="7" height="5" rx="1.5" />
            <rect x="14" y="12" width="7" height="9" rx="1.5" />
            <rect x="3" y="16" width="7" height="5" rx="1.5" />
        </template>
        <template v-else-if="name === 'negociacoes'">
            <rect x="3" y="4" width="5" height="13" rx="1.5" />
            <rect x="10" y="4" width="5" height="9" rx="1.5" />
            <rect x="17" y="4" width="4" height="6" rx="1.5" />
        </template>
        <template v-else-if="name === 'atividades'">
            <path d="M4 6.5l2 2 3.5-3.5" />
            <path d="M4 13l2 2 3.5-3.5" />
            <path d="M4 19.5l2 2 3.5-3.5" />
            <path d="M13 7h8M13 14h8M13 20.5h6" />
        </template>
        <template v-else-if="name === 'calendario'">
            <rect x="3" y="5" width="18" height="16" rx="2" />
            <path d="M3 10h18M8 3v4M16 3v4" />
            <path d="M7.5 14h2M11 14h2M14.5 14h2M7.5 17.5h2M11 17.5h2" />
        </template>
        <template v-else-if="name === 'contatos'">
            <circle cx="9" cy="8" r="3.4" />
            <path d="M3.5 20c0-3.2 2.5-5.2 5.5-5.2s5.5 2 5.5 5.2" />
            <path d="M16.5 6.2a3 3 0 0 1 0 5.6" />
            <path d="M18 14.9c2 .6 3.4 2.4 3.4 5.1" />
        </template>
        <template v-else-if="name === 'empresas'">
            <path
                d="M3 21V6.5a1.5 1.5 0 0 1 1.5-1.5h6A1.5 1.5 0 0 1 12 6.5V21"
            />
            <path d="M12 10h7.5A1.5 1.5 0 0 1 21 11.5V21" />
            <path d="M2 21h20" />
            <path d="M6 9h3M6 13h3M6 17h3M15.5 14h2M15.5 17.5h2" />
        </template>
        <template v-else-if="name === 'propostas'">
            <path d="M6 3h8l4 4v14H6z" />
            <path d="M14 3v4h4" />
            <path d="M9 12h6M9 16h4" />
        </template>
        <template v-else-if="name === 'automacoes'">
            <path d="M13 2 4.5 13.5H11L9.5 22 19 9.5h-6.5z" />
        </template>
        <template v-else-if="name === 'trafego'">
            <circle cx="12" cy="12" r="9" />
            <path d="M3 12h18" />
            <path d="M12 3c2.6 2.6 2.6 15.4 0 18-2.6-2.6-2.6-15.4 0-18z" />
        </template>
        <template v-else-if="name === 'investimento'">
            <ellipse cx="12" cy="6" rx="7" ry="3" />
            <path d="M5 6v5c0 1.7 3.1 3 7 3s7-1.3 7-3V6" />
            <path d="M5 11v5c0 1.7 3.1 3 7 3s7-1.3 7-3v-5" />
        </template>
        <template v-else-if="name === 'site'">
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <path d="M3 9h18" />
            <path d="M7 6.5h.01M9.5 6.5h.01" />
        </template>
        <template v-else-if="name === 'compliance'">
            <path d="M12 3l7 3v6c0 4.3-2.9 7.6-7 9-4.1-1.4-7-4.7-7-9V6z" />
            <path d="M9 12l2.2 2.2L15.5 10" />
        </template>
        <template v-else-if="name === 'admin'">
            <circle cx="12" cy="12" r="3" />
            <path
                d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"
            />
        </template>
    </svg>
</template>
