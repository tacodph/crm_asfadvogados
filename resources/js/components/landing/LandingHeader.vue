<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LogIn, Menu, X } from '@lucide/vue';
import { BRAND } from '@/lib/brand';
import { login } from '@/routes';

defineProps<{
    isSticky: boolean;
    mobileMenuOpen: boolean;
    activeSection: string;
}>();

const emit = defineEmits<{
    toggleMobileMenu: [];
    closeMobileMenu: [];
}>();

const navLinks = [
    { href: '#home', id: 'home', label: 'Início' },
    { href: '#features', id: 'features', label: 'Recursos' },
    { href: '#about', id: 'about', label: 'Sobre' },
    { href: '#pricing', id: 'pricing', label: 'Planos' },
    { href: '#contact', id: 'contact', label: 'Contato' },
];
</script>

<template>
    <nav id="navbar" class="landing-nav" :class="{ 'is-sticky': isSticky }">
        <div class="landing-container flex w-full items-center self-center">
            <div class="shrink-0">
                <a
                    href="#home"
                    class="flex items-center"
                    @click="emit('closeMobileMenu')"
                >
                    <img
                        :src="BRAND.wordmarkLightTransparent"
                        :alt="BRAND.name"
                        class="h-8 w-auto object-contain"
                    />
                </a>
            </div>

            <div class="mx-auto">
                <ul
                    id="navbar-menu"
                    class="navbar-menu absolute inset-x-0 top-full z-20 rounded-b-md bg-white py-3 shadow-lg md:relative md:top-auto md:flex md:rounded-none md:bg-transparent md:py-0 md:shadow-none"
                    :class="mobileMenuOpen ? 'block' : 'hidden md:flex'"
                >
                    <li v-for="link in navLinks" :key="link.id">
                        <a
                            :href="link.href"
                            class="landing-nav-link"
                            :class="{ active: activeSection === link.id }"
                            @click="emit('closeMobileMenu')"
                        >
                            {{ link.label }}
                        </a>
                    </li>
                </ul>
            </div>

            <div class="flex gap-2 ltr:ml-auto rtl:mr-auto">
                <div class="md:hidden">
                    <button
                        type="button"
                        class="landing-btn landing-btn-primary size-[37.5px] p-0"
                        @click="emit('toggleMobileMenu')"
                    >
                        <Menu v-if="!mobileMenuOpen" class="size-4" />
                        <X v-else class="size-4" />
                    </button>
                </div>
                <Link
                    :href="login()"
                    class="landing-btn landing-btn-primary hidden sm:inline-flex"
                >
                    <span>Entrar</span>
                    <LogIn class="ml-1 inline-block size-4" />
                </Link>
            </div>
        </div>
    </nav>
</template>
