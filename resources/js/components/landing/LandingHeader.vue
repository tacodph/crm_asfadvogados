<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { login } from '@/routes';

const navLinks = [
    { href: '#produto', label: 'Produto' },
    { href: '#compliance', label: 'Compliance' },
    { href: '#planos', label: 'Planos' },
    { href: '#perguntas', label: 'Perguntas' },
];
</script>

<template>
    <header
        class="sticky top-0 z-40 border-b border-[#E7E3DA] bg-[rgba(247,245,240,0.92)] backdrop-blur-[10px]"
    >
        <div
            class="mx-auto flex max-w-[1180px] items-center justify-between gap-5 px-7 py-[14px]"
        >
            <a href="/" class="flex items-center gap-[11px]">
                <span
                    class="grid h-[30px] w-[30px] place-items-center rounded-lg bg-[#171B21] font-[family-name:var(--font-landing-display)] text-[15px] text-white"
                >
                    A
                </span>
                <span
                    class="font-[family-name:var(--font-landing-display)] text-[18px] tracking-[-0.01em]"
                >
                    Antessala
                </span>
                <span
                    class="hidden rounded-full border border-[#E2D3B6] bg-[#F6EFE2] px-2 py-[3px] font-[family-name:var(--font-landing-mono)] text-[9px] tracking-[0.12em] text-[#8C6F3F] uppercase sm:inline"
                >
                    CRM jurídico
                </span>
            </a>

            <nav class="hidden items-center gap-[26px] md:flex">
                <a
                    v-for="link in navLinks"
                    :key="link.href"
                    :href="link.href"
                    class="text-[13.5px] text-[#3C4450] transition-colors hover:text-[#171B21]"
                >
                    {{ link.label }}
                </a>
            </nav>

            <div class="flex items-center gap-2.5">
                <Link
                    :href="login()"
                    class="rounded-lg border border-[#E3DFD6] bg-white px-[13px] py-2 text-[13.5px] text-[#3C4450] transition-colors hover:border-[#D8D2C5] hover:text-[#171B21]"
                >
                    Entrar
                </Link>
                <a
                    href="#demonstracao"
                    class="rounded-lg bg-[#171B21] px-[15px] py-[9px] text-[13.5px] font-medium text-white transition-colors hover:bg-[#2B313A] hover:text-white"
                >
                    Agendar demonstração
                </a>
            </div>
        </div>
    </header>
</template>
