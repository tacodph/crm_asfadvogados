<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as usersIndex } from '@/routes/settings/users';
import type { NavItem } from '@/types';

const page = usePage();

const canManageUsers = computed(() => {
    const role = page.props.auth.user?.role;

    return role === 'owner' || role === 'admin';
});

const sidebarNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Perfil',
            href: editProfile(),
        },
        {
            title: 'Segurança',
            href: editSecurity(),
        },
        {
            title: 'Aparência',
            href: editAppearance(),
        },
    ];

    if (canManageUsers.value) {
        items.splice(1, 0, {
            title: 'Usuários',
            href: usersIndex(),
        });
    }

    return items;
});

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="flex max-w-[960px] flex-col gap-5">
        <div class="flex flex-col gap-1">
            <h2
                class="m-0 font-[family-name:var(--font-crm-display)] text-[18px] font-medium tracking-[-0.01em] text-foreground"
            >
                Conta
            </h2>
            <p class="m-0 text-[13px] text-muted-foreground">
                Gerencie perfil, segurança e preferências do escritório.
            </p>
        </div>

        <div class="flex flex-col gap-5 lg:flex-row lg:gap-8">
            <aside class="w-full shrink-0 lg:w-44">
                <nav
                    class="flex flex-row gap-1 overflow-x-auto lg:flex-col"
                    aria-label="Configurações"
                >
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        class="rounded-lg px-3 py-2 text-[13px] whitespace-nowrap"
                        :class="
                            isCurrentOrParentUrl(item.href)
                                ? 'bg-primary/15 font-medium text-foreground'
                                : 'text-muted-foreground hover:bg-secondary hover:text-foreground'
                        "
                    >
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <div
                class="min-w-0 flex-1 rounded-[10px] border border-border bg-card px-5 py-5"
            >
                <section class="w-full space-y-10">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
