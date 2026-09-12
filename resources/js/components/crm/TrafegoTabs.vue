<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { index as diagnostico } from '@/routes/trafego/diagnostico';
import { index as eventos } from '@/routes/trafego/eventos';
import { index as investimento } from '@/routes/trafego/investimento';
import { index as config } from '@/routes/trafego';

const page = usePage();

const abas = [
    { label: 'Configuração', href: config(), match: 'crm/Trafego' },
    { label: 'Eventos', href: eventos(), match: 'crm/TrafegoEventos' },
    { label: 'Diagnóstico', href: diagnostico(), match: 'crm/TrafegoDiagnostico' },
    { label: 'Investimento', href: investimento(), match: 'crm/TrafegoInvestimento' },
];
</script>

<template>
    <nav class="flex gap-1 border-b border-border">
        <Link
            v-for="aba in abas"
            :key="aba.match"
            :href="aba.href"
            class="-mb-px border-b-2 px-3 py-2 text-[13px]"
            :class="
                page.component === aba.match
                    ? 'border-primary font-medium text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground'
            "
        >
            {{ aba.label }}
        </Link>
    </nav>
</template>
