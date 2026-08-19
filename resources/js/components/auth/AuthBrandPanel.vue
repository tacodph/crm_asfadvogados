<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AuthLogoMark from '@/components/auth/AuthLogoMark.vue';
import { home } from '@/routes';
</script>

<template>
    <aside
        class="relative hidden shrink-0 flex-col justify-between overflow-hidden bg-[#14181E] px-[52px] py-[46px] lg:flex lg:basis-[46%]"
    >
        <div
            class="pointer-events-none absolute -top-40 -right-[180px] h-[520px] w-[520px] rounded-full bg-[radial-gradient(circle,rgba(199,154,78,0.13),transparent_68%)]"
        />

        <Link :href="home()" class="auth-brand-link relative">
            <AuthLogoMark />
        </Link>

        <div class="relative flex max-w-[420px] flex-col gap-6">
            <h1
                class="m-0 font-[family-name:var(--font-auth-display)] text-[40px] leading-[1.16] font-normal tracking-[-0.02em] text-[#FBF9F4]"
            >
                O momento comercial da advocacia, sob controle.
            </h1>
            <p class="m-0 text-[14.5px] leading-[1.7] text-[#A8AFB9]">
                Funis configuráveis, histórico unificado de contato e trilha de
                auditoria — do primeiro contato ao contrato assinado, dentro dos
                limites do Código de Ética da OAB e da LGPD.
            </p>
            <div class="flex gap-7 pt-1.5">
                <div class="flex flex-col gap-[5px]">
                    <span
                        class="font-[family-name:var(--font-auth-display)] text-[26px] leading-none text-[#C79A4E]"
                    >
                        24
                    </span>
                    <span class="text-[11.5px] text-[#7C8593]">
                        Estados atendidos
                    </span>
                </div>
                <div class="flex flex-col gap-[5px]">
                    <span
                        class="font-[family-name:var(--font-auth-display)] text-[26px] leading-none text-[#C79A4E]"
                    >
                        3.000+
                    </span>
                    <span class="text-[11.5px] text-[#7C8593]">
                        Clientes atendidos
                    </span>
                </div>
                <div class="flex flex-col gap-[5px]">
                    <span
                        class="font-[family-name:var(--font-auth-display)] text-[26px] leading-none text-[#C79A4E]"
                    >
                        2
                    </span>
                    <span class="text-[11.5px] text-[#7C8593]">
                        Áreas de atuação
                    </span>
                </div>
            </div>
        </div>

        <div class="relative flex items-center gap-[9px]">
            <span class="h-1.5 w-1.5 rounded-full bg-[#4E9C87]" />
            <span class="text-[11.5px] text-[#7C8593]">
                Acesso registrado na trilha de auditoria · sessão expira em 8h
            </span>
        </div>
    </aside>
</template>
