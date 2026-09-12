<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    Bell,
    ChevronsLeft,
    ChevronsRight,
    LogOut,
    Moon,
    Palette,
    Search,
    Settings,
    Shield,
    Sun,
    UserRound,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/composables/useAppearance';
import { getInitials } from '@/composables/useInitials';
import { SCREEN_TITLES, type ScreenKey } from '@/data/crm';
import { logout } from '@/routes';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { index as usersIndex } from '@/routes/settings/users';
import type { User } from '@/types';

const colapsado = defineModel<boolean>('colapsado', { default: false });

const { screen, periodo = '30 dias' } = defineProps<{
    screen: ScreenKey;
    periodo?: string;
}>();

const page = usePage();
const user = computed(() => page.props.auth.user as User);
const iniciais = computed(() => getInitials(user.value?.name) || 'LS');
const titulo = computed(() => SCREEN_TITLES[screen][0]);
const subtitulo = computed(() =>
    SCREEN_TITLES[screen][1].replace('{periodo}', periodo),
);
const canManageUsers = computed(() => {
    const role = user.value?.role;

    return role === 'owner' || role === 'admin';
});

const { resolvedAppearance, updateAppearance } = useAppearance();

function toggleColapso() {
    colapsado.value = !colapsado.value;
}

function toggleTheme() {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
}

function handleLogout() {
    router.flushAll();
}
</script>

<template>
    <header
        class="relative z-10 flex h-[70px] shrink-0 items-center gap-3 border-b border-border bg-card px-4 shadow-sm shadow-slate-200/50 dark:shadow-none md:px-5"
    >
        <button
            type="button"
            class="topbar-icon-btn"
            :title="colapsado ? 'Expandir menu' : 'Minimizar menu'"
            @click="toggleColapso"
        >
            <ChevronsRight v-if="colapsado" class="size-5" />
            <ChevronsLeft v-else class="size-5" />
        </button>

        <div class="hidden min-w-0 flex-col leading-tight sm:flex">
            <h1 class="m-0 truncate text-[15px] font-semibold text-foreground">
                {{ titulo }}
            </h1>
            <span class="truncate text-[11.5px] text-muted-foreground">
                {{ subtitulo }}
            </span>
        </div>

        <div class="relative mx-auto hidden max-w-md flex-1 lg:block">
            <Search
                class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <input
                type="search"
                placeholder="Search for ..."
                class="h-9 w-full rounded-md border border-border bg-card pr-3 pl-8 text-sm text-foreground placeholder:text-slate-400 outline-none focus:border-primary"
            />
        </div>

        <div class="ml-auto flex items-center gap-1.5 sm:gap-2">
            <div
                class="mr-1 hidden items-center gap-1.5 rounded-full border border-accent/20 bg-accent/10 px-2.5 py-1 xl:flex"
            >
                <span class="h-1.5 w-1.5 rounded-full bg-accent" />
                <span class="text-[11px] font-medium text-accent">
                    Compliance OAB
                </span>
            </div>

            <button
                type="button"
                class="topbar-icon-btn"
                title="Alternar tema"
                @click="toggleTheme"
            >
                <Moon
                    v-if="resolvedAppearance === 'light'"
                    class="size-[18px]"
                />
                <Sun v-else class="size-[18px]" />
            </button>

            <button type="button" class="topbar-icon-btn" title="Notificações">
                <Bell class="size-[18px]" />
            </button>

            <Link
                :href="editAppearance()"
                class="topbar-icon-btn"
                title="Aparência"
            >
                <Settings class="size-[18px]" />
            </Link>

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button
                        type="button"
                        data-test="crm-user-menu"
                        class="ml-1 flex items-center gap-2 rounded-md px-1.5 py-1 hover:bg-slate-100 dark:hover:bg-zink-600"
                        :title="user?.name"
                    >
                        <span
                            class="grid size-8 place-items-center rounded-full bg-primary/15 text-[11px] font-semibold text-primary"
                        >
                            {{ iniciais }}
                        </span>
                        <span class="hidden max-w-[120px] truncate text-left text-[12.5px] font-medium text-foreground lg:block">
                            {{ user?.name }}
                        </span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-56 rounded-lg border-border bg-popover"
                    align="end"
                    :side-offset="8"
                >
                    <DropdownMenuLabel class="p-0 font-normal">
                        <div class="flex flex-col gap-0.5 px-2 py-2 text-left">
                            <span
                                class="truncate text-[13px] font-medium text-foreground"
                            >
                                {{ user?.name }}
                            </span>
                            <span
                                class="truncate text-[11.5px] text-muted-foreground"
                            >
                                {{ user?.email }}
                            </span>
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator class="bg-border" />
                    <DropdownMenuGroup>
                        <DropdownMenuItem :as-child="true">
                            <Link
                                class="flex w-full cursor-pointer items-center"
                                :href="editProfile()"
                                prefetch
                            >
                                <UserRound class="mr-2 size-4" />
                                Perfil
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            v-if="canManageUsers"
                            :as-child="true"
                        >
                            <Link
                                class="flex w-full cursor-pointer items-center"
                                :href="usersIndex()"
                                prefetch
                            >
                                <Users class="mr-2 size-4" />
                                Usuários
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem :as-child="true">
                            <Link
                                class="flex w-full cursor-pointer items-center"
                                :href="editSecurity()"
                                prefetch
                            >
                                <Shield class="mr-2 size-4" />
                                Segurança
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem :as-child="true">
                            <Link
                                class="flex w-full cursor-pointer items-center"
                                :href="editAppearance()"
                                prefetch
                            >
                                <Palette class="mr-2 size-4" />
                                Aparência
                            </Link>
                        </DropdownMenuItem>
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator class="bg-border" />
                    <DropdownMenuItem :as-child="true">
                        <Link
                            class="flex w-full cursor-pointer items-center"
                            :href="logout()"
                            as="button"
                            data-test="crm-logout-button"
                            @click="handleLogout"
                        >
                            <LogOut class="mr-2 size-4" />
                            Sair
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
