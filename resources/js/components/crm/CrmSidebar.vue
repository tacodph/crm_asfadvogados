<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CrmNavIcon from '@/components/crm/CrmNavIcon.vue';
import { NAV_GROUPS } from '@/data/crm';
import { getInitials } from '@/composables/useInitials';
import {
    isNavEnabled,
    PAGE_SCREEN,
    SCREEN_HREF,
    screenCount,
} from '@/lib/crmNav';
import { index as contatos } from '@/routes/contatos';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

const colapsado = defineModel<boolean>('colapsado', { default: false });

const page = usePage();
const user = computed(() => page.props.auth.user as User);
const iniciais = computed(() => getInitials(user.value?.name) || 'CM');
const screenAtiva = computed(() => PAGE_SCREEN[page.component] ?? null);
const counts = computed(() => page.props.crmCounts);

function toggleColapso() {
    colapsado.value = !colapsado.value;
}
</script>

<template>
    <aside
        class="flex h-full shrink-0 flex-col border-r border-[#E7E3DA] bg-white py-[14px] transition-[width] duration-150 ease-linear"
        :style="{
            width: colapsado ? '68px' : '232px',
            flexBasis: colapsado ? '68px' : '232px',
        }"
    >
        <div
            class="mb-4 flex items-center gap-2.5 px-[14px]"
            :class="colapsado ? 'justify-center' : 'justify-between'"
        >
            <Link :href="contatos()" class="flex min-w-0 items-center gap-2.5">
                <span
                    class="grid h-[30px] w-[30px] shrink-0 place-items-center rounded-lg bg-[#171B21] font-[family-name:var(--font-crm-display)] text-[13px] tracking-[0.02em] text-[#FBF9F4]"
                >
                    ASF
                </span>
                <span
                    v-if="!colapsado"
                    class="flex min-w-0 flex-col leading-[1.2]"
                >
                    <span
                        class="font-[family-name:var(--font-crm-display)] text-[14px] tracking-[0.04em] whitespace-nowrap text-[#171B21] uppercase"
                    >
                        ASF Advogados
                    </span>
                    <span
                        class="font-[family-name:var(--font-crm-mono)] text-[9px] tracking-[0.12em] text-[#9AA2AE] uppercase"
                    >
                        CRM Jurídico
                    </span>
                </span>
            </Link>
            <button
                v-if="!colapsado"
                type="button"
                title="Minimizar menu"
                class="h-6 w-6 shrink-0 rounded-md border border-[#E7E3DA] bg-white text-[13px] leading-none text-[#77808E]"
                @click="toggleColapso"
            >
                ‹
            </button>
        </div>

        <nav
            class="flex flex-1 flex-col gap-3.5 overflow-y-auto"
            :class="colapsado ? 'px-2' : 'px-2.5'"
        >
            <div
                v-for="grupo in NAV_GROUPS"
                :key="grupo.title"
                class="flex flex-col gap-0.5"
            >
                <span
                    v-if="!colapsado"
                    class="px-3 pb-[5px] font-[family-name:var(--font-crm-mono)] text-[9px] tracking-[0.13em] text-[#A9A296] uppercase"
                >
                    {{ grupo.title }}
                </span>

                <component
                    :is="isNavEnabled(item.key) ? Link : 'span'"
                    v-for="item in grupo.items"
                    :key="item.key"
                    :href="SCREEN_HREF[item.key]"
                    :aria-disabled="isNavEnabled(item.key) ? undefined : true"
                    :title="
                        isNavEnabled(item.key)
                            ? item.label
                            : `${item.label} — em breve`
                    "
                    class="flex w-full items-center gap-2.5 rounded-lg text-left text-[13px]"
                    :class="[
                        colapsado
                            ? 'justify-center px-0 py-2.5'
                            : 'justify-between px-3 py-[9px]',
                        isNavEnabled(item.key) && screenAtiva === item.key
                            ? 'cursor-pointer bg-[#EFEBE2] font-medium text-[#171B21]'
                            : isNavEnabled(item.key)
                              ? 'cursor-pointer font-normal text-[#5F6875] hover:bg-[#F1EEE7]'
                              : 'cursor-default font-normal text-[#C4BDB0]',
                    ]"
                >
                    <span class="flex min-w-0 items-center gap-2.5">
                        <CrmNavIcon :name="item.key" />
                        <span v-if="!colapsado" class="whitespace-nowrap">
                            {{ item.label }}
                        </span>
                    </span>
                    <span
                        v-if="!colapsado && screenCount(item.key, counts)"
                        class="font-[family-name:var(--font-crm-mono)] text-[10.5px]"
                        :class="
                            isNavEnabled(item.key) && screenAtiva === item.key
                                ? 'text-[#8C6F3F]'
                                : 'text-[#9AA2AE]'
                        "
                    >
                        {{ screenCount(item.key, counts) }}
                    </span>
                </component>
            </div>
        </nav>

        <div
            class="mt-auto flex items-center gap-2.5 border-t border-[#EEEBE4] px-[14px] pt-3.5"
            :class="colapsado ? 'flex-col justify-center' : 'justify-between'"
        >
            <Link
                :href="edit()"
                class="flex min-w-0 items-center gap-2.5"
                :title="user?.name"
            >
                <span
                    class="grid h-[30px] w-[30px] shrink-0 place-items-center rounded-full bg-[#EFE7D8] text-[11.5px] font-semibold text-[#6F5730]"
                >
                    {{ iniciais }}
                </span>
                <span
                    v-if="!colapsado"
                    class="flex min-w-0 flex-col leading-[1.25]"
                >
                    <span class="truncate text-[12.5px] text-[#171B21]">
                        {{ user?.name }}
                    </span>
                    <span
                        class="text-[10.5px] whitespace-nowrap text-[#9AA2AE]"
                    >
                        Sócia · Gestor
                    </span>
                </span>
            </Link>
            <button
                v-if="colapsado"
                type="button"
                title="Expandir menu"
                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-[#E7E3DA] bg-white text-[13px] leading-none text-[#77808E]"
                @click="toggleColapso"
            >
                ›
            </button>
        </div>
    </aside>
</template>
