<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { login } from '@/routes';

const footerLinks = [
    { href: '#produto', label: 'Produto' },
    { href: '#compliance', label: 'Compliance' },
    { href: '#planos', label: 'Planos' },
];
</script>

<template>
    <footer class="border-t border-[#E7E3DA] bg-[#FBFAF7]">
        <div
            class="mx-auto flex max-w-[1180px] flex-wrap items-center justify-between gap-6 px-7 py-[34px]"
        >
            <div class="flex items-baseline gap-2.5">
                <span
                    class="font-[family-name:var(--font-landing-display)] text-base"
                >
                    Antessala
                </span>
                <span class="text-[12.5px] text-[#9AA2AE]">
                    CRM jurídico pré-processual
                </span>
            </div>
            <div class="flex flex-wrap gap-[22px]">
                <a
                    v-for="link in footerLinks"
                    :key="link.href"
                    :href="link.href"
                    class="text-[12.5px] text-[#77808E] transition-colors hover:text-[#8C6F3F]"
                >
                    {{ link.label }}
                </a>
                <Link
                    :href="login()"
                    class="text-[12.5px] text-[#77808E] transition-colors hover:text-[#8C6F3F]"
                >
                    Entrar
                </Link>
            </div>
            <span class="text-[11.5px] text-[#A9A296]">
                © 2026 Antessala Tecnologia · dados hospedados no Brasil
            </span>
        </div>
    </footer>
</template>
