<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import CrmNavIcon from '@/components/crm/CrmNavIcon.vue';
import { NAV_GROUPS } from '@/data/crm';
import {
    isNavEnabled,
    PAGE_SCREEN,
    screenCount,
    screenHref,
} from '@/lib/crmNav';
import { BRAND } from '@/lib/brand';
import { dashboard } from '@/routes';

const colapsado = defineModel<boolean>('colapsado', { default: false });

const page = usePage();
const screenAtiva = computed(() => PAGE_SCREEN[page.component] ?? null);
const counts = computed(() => page.props.crmCounts);
</script>

<template>
    <aside
        class="relative z-20 flex h-full shrink-0 flex-col border-r border-sidebar-border bg-sidebar transition-[width] duration-150 ease-linear"
        :style="{
            width: colapsado ? '70px' : '260px',
            flexBasis: colapsado ? '70px' : '260px',
        }"
    >
        <div
            class="flex h-[70px] items-center border-b border-sidebar-border px-3.5"
            :class="colapsado ? 'justify-center' : 'justify-start'"
        >
            <Link :href="dashboard()" class="flex min-w-0 items-center gap-2.5">
                <img
                    :src="BRAND.mark"
                    :alt="BRAND.name"
                    class="h-8 w-8 shrink-0 rounded-full object-contain"
                />
                <span
                    v-if="!colapsado"
                    class="flex min-w-0 flex-col leading-[1.15]"
                >
                    <span
                        class="truncate text-[15px] font-semibold tracking-wide text-slate-800 dark:text-sidebar-foreground"
                    >
                        {{ BRAND.name }}
                    </span>
                    <span
                        class="font-[family-name:var(--font-crm-mono)] text-[9px] tracking-[0.12em] text-muted-foreground uppercase"
                    >
                        {{ BRAND.tagline }}
                    </span>
                </span>
            </Link>
        </div>

        <nav
            class="flex flex-1 flex-col gap-3 overflow-y-auto py-3"
            :class="colapsado ? 'px-2' : 'px-2.5'"
        >
            <div
                v-for="grupo in NAV_GROUPS"
                :key="grupo.title"
                class="flex flex-col gap-0.5"
            >
                <span
                    v-if="!colapsado"
                    class="px-3 pb-1.5 text-[10px] font-semibold tracking-[0.14em] text-slate-400 uppercase"
                >
                    {{ grupo.title }}
                </span>

                <component
                    :is="isNavEnabled(item.key) ? Link : 'span'"
                    v-for="item in grupo.items"
                    :key="item.key"
                    :href="screenHref(item.key)"
                    :aria-disabled="isNavEnabled(item.key) ? undefined : true"
                    :title="
                        isNavEnabled(item.key)
                            ? item.label
                            : `${item.label} — em breve`
                    "
                    class="group/menu-link flex w-full items-center gap-2.5 rounded-md text-left text-[13.5px] transition-colors"
                    :class="[
                        colapsado
                            ? 'justify-center px-0 py-2.5'
                            : 'justify-between px-3 py-2.5',
                        isNavEnabled(item.key) && screenAtiva === item.key
                            ? 'cursor-pointer bg-primary/10 font-medium text-primary'
                            : isNavEnabled(item.key)
                              ? 'cursor-pointer font-normal text-slate-500 hover:bg-slate-50 hover:text-primary dark:text-sidebar-foreground/80 dark:hover:bg-sidebar-accent'
                              : 'cursor-default font-normal text-slate-300 dark:text-muted-foreground/40',
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
                        class="text-[10.5px]"
                        :class="
                            isNavEnabled(item.key) && screenAtiva === item.key
                                ? 'text-primary'
                                : 'text-slate-400'
                        "
                    >
                        {{ screenCount(item.key, counts) }}
                    </span>
                </component>
            </div>
        </nav>
    </aside>
</template>
