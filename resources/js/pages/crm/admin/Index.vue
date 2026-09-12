<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { index as canaisIndex } from '@/routes/admin/canais-contato';
import { index as finalidadesIndex } from '@/routes/admin/finalidades-consentimento';
import { index as funisIndex } from '@/routes/admin/funis';
import { index as setoresIndex } from '@/routes/admin/setores';
import { index as statusComercialIndex } from '@/routes/admin/status-comerciais';
import { index as statusConflitoIndex } from '@/routes/admin/status-conflitos';
import { index as statusIndex } from '@/routes/admin/status-consentimentos';
import { index as tiposPessoaIndex } from '@/routes/admin/tipos-pessoa';

type ModuloKey =
    | 'setores'
    | 'canais'
    | 'status-conflito'
    | 'status-comercial'
    | 'tipos-pessoa'
    | 'finalidades'
    | 'status'
    | 'funis';

type Modulo = {
    key: ModuloKey;
    titulo: string;
    descricao: string;
};

defineProps<{
    modulos: Modulo[];
}>();

const hrefFor = (key: ModuloKey) => {
    switch (key) {
        case 'setores':
            return setoresIndex();
        case 'canais':
            return canaisIndex();
        case 'status-conflito':
            return statusConflitoIndex();
        case 'status-comercial':
            return statusComercialIndex();
        case 'tipos-pessoa':
            return tiposPessoaIndex();
        case 'finalidades':
            return finalidadesIndex();
        case 'funis':
            return funisIndex();
        default:
            return statusIndex();
    }
};
</script>

<template>
    <div class="mx-auto flex max-w-[900px] flex-col gap-4">
        <div class="flex flex-col gap-1">
            <h1
                class="m-0 font-[family-name:var(--font-crm-display)] text-[22px] font-medium tracking-[-0.01em] text-foreground"
            >
                Administração
            </h1>
            <p class="m-0 text-[13px] text-muted-foreground">
                Cadastro e edição das tabelas de domínio do escritório.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <Link
                v-for="modulo in modulos"
                :key="modulo.key"
                :href="hrefFor(modulo.key)"
                class="flex flex-col gap-1.5 rounded-[10px] border border-border bg-card px-4 py-4 hover:border-primary"
            >
                <span class="text-[14px] font-medium text-foreground">
                    {{ modulo.titulo }}
                </span>
                <span class="text-[12.5px] text-muted-foreground">
                    {{ modulo.descricao }}
                </span>
            </Link>
        </div>
    </div>
</template>
