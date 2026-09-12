<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BRAND } from '@/lib/brand';
import { login } from '@/routes';

const produtoLinks = [
    { href: '#features', label: 'Recursos' },
    { href: '#about', label: 'Sobre' },
    { href: '#pricing', label: 'Planos' },
    { href: '#contact', label: 'Contato' },
];

const recursosLinks = [
    { href: '#about', label: 'Compliance OAB' },
    { href: '#features', label: 'Funis configuráveis' },
    { href: '#features', label: 'Auditoria' },
    { href: '#pricing', label: 'Preços' },
];
</script>

<template>
    <footer class="relative bg-slate-800 pb-12 pt-20">
        <div class="landing-container">
            <div class="relative z-10 grid grid-cols-12 gap-5">
                <div class="col-span-12 md:col-span-6 lg:col-span-4 xl:col-span-3">
                    <div class="mb-4 flex items-center gap-2">
                        <img
                            :src="BRAND.mark"
                            :alt="BRAND.name"
                            class="size-8 rounded-full object-contain"
                        />
                        <span class="font-medium text-slate-50">
                            {{ BRAND.name }}
                        </span>
                    </div>
                    <p class="text-15 text-slate-400">
                        CRM jurídico pré-processual com compliance desde a
                        arquitetura.
                    </p>
                </div>

                <div class="col-span-12 md:col-span-6 lg:col-span-4 xl:col-span-3">
                    <h5 class="mb-4 font-medium tracking-wider text-slate-50">
                        Produto
                    </h5>
                    <ul class="flex flex-col gap-3 text-15">
                        <li v-for="link in produtoLinks" :key="link.label">
                            <a
                                :href="link.href"
                                class="text-slate-400 transition-colors hover:text-slate-300"
                            >
                                {{ link.label }}
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="col-span-12 md:col-span-6 lg:col-span-4 xl:col-span-3">
                    <h5 class="mb-4 font-medium tracking-wider text-slate-50">
                        Recursos
                    </h5>
                    <ul class="flex flex-col gap-3 text-15">
                        <li v-for="link in recursosLinks" :key="link.label">
                            <a
                                :href="link.href"
                                class="text-slate-400 transition-colors hover:text-slate-300"
                            >
                                {{ link.label }}
                            </a>
                        </li>
                        <li>
                            <Link
                                :href="login()"
                                class="text-slate-400 transition-colors hover:text-slate-300"
                            >
                                Entrar
                            </Link>
                        </li>
                    </ul>
                </div>

                <div class="col-span-12 md:col-span-6 lg:col-span-12 xl:col-span-3">
                    <form class="relative mb-6" @submit.prevent>
                        <input
                            type="email"
                            class="w-full rounded-md border border-slate-700 bg-slate-700/60 py-3 pr-36 pl-3 text-slate-200 backdrop-blur-md placeholder:text-slate-500 focus:border-custom-500 focus:outline-none"
                            placeholder="seu@escritorio.adv.br"
                            autocomplete="off"
                        />
                        <button
                            type="submit"
                            class="landing-btn landing-btn-primary absolute top-1 bottom-1 ltr:right-1 rtl:left-1 px-4 py-2 text-sm"
                        >
                            Inscrever
                        </button>
                    </form>
                    <p class="mb-1 text-15 text-slate-500">Suporte</p>
                    <h6 class="text-lg font-normal text-slate-200">
                        contato@lexstart.com.br
                    </h6>
                </div>
            </div>

            <div class="mt-12 text-center text-16 text-slate-400">
                <p>
                    © {{ new Date().getFullYear() }} {{ BRAND.name }} · Dados
                    hospedados no Brasil
                </p>
            </div>
        </div>
    </footer>
</template>
