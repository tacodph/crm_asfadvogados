<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Rocket } from '@lucide/vue';
import { onUnmounted, reactive, ref } from 'vue';
import { tamanhosTime } from '@/data/landing';
import { BRAND } from '@/lib/brand';
import { create } from '@/routes/tenants';

const form = reactive({
    escritorio: '',
    email: '',
    tamanho: '3 a 8 pessoas',
});

const enviado = ref(false);
const erroForm = ref<string | null>(null);
const toast = ref<string | null>(null);

let toastTimer: ReturnType<typeof setTimeout> | undefined;

function flash(texto: string): void {
    clearTimeout(toastTimer);
    toast.value = texto;
    toastTimer = setTimeout(() => {
        toast.value = null;
    }, 2800);
}

function enviar(): void {
    if (!form.escritorio.trim()) {
        erroForm.value = 'Informe o nome do escritório.';

        return;
    }

    if (!/^[^@\s]+@[^@\s]+\.[a-z]{2,}$/i.test(form.email)) {
        erroForm.value = 'Informe um e-mail profissional válido.';

        return;
    }

    enviado.value = true;
    erroForm.value = null;
    flash('Pedido de demonstração registrado.');
}

onUnmounted(() => {
    clearTimeout(toastTimer);
});
</script>

<template>
    <section id="contact" class="relative bg-custom-600 py-20">
        <div
            class="absolute -bottom-[250px] hidden size-[500px] rotate-45 rounded-full border border-b-slate-700 border-l-custom-500 border-r-slate-700 border-t-custom-500 border-dashed ltr:right-40 lg:block"
        />
        <div class="landing-container relative z-10">
            <div class="grid grid-cols-1 items-center gap-5 lg:grid-cols-12">
                <div class="lg:col-span-9">
                    <h2 class="mb-4 capitalize leading-normal text-custom-50">
                        Pronto para começar com o {{ BRAND.name }}?
                    </h2>
                    <p class="text-lg text-custom-200">
                        Demonstração de 30 minutos com seus funis já montados —
                        ou crie sua conta e comece agora.
                    </p>
                </div>
                <div class="lg:col-span-3 ltr:lg:text-right rtl:lg:text-left">
                    <Link
                        :href="create()"
                        class="landing-btn relative z-10 border-white bg-white text-custom-500 hover:bg-white"
                    >
                        <Rocket class="mr-1 inline-block size-4" />
                        Criar conta grátis
                    </Link>
                </div>
            </div>

            <div
                class="mt-12 grid items-start gap-8 rounded-xl bg-white p-8 lg:grid-cols-2"
            >
                <div>
                    <h3 class="mb-3 text-xl font-semibold text-slate-800">
                        Agendar demonstração
                    </h3>
                    <p class="text-slate-500">
                        Sem cartão de crédito · Piloto de 30 dias · Dados
                        hospedados no Brasil
                    </p>
                </div>

                <div v-if="enviado" class="rounded-lg border border-slate-200 p-6">
                    <p class="font-medium text-slate-800">Pedido recebido</p>
                    <p class="mt-2 text-sm text-slate-500">
                        Entramos em contato em até um dia útil no e-mail
                        informado.
                    </p>
                </div>

                <form
                    v-else
                    class="flex flex-col gap-3"
                    @submit.prevent="enviar"
                >
                    <input
                        v-model="form.escritorio"
                        type="text"
                        placeholder="Nome do escritório"
                        class="rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-custom-500"
                        @input="erroForm = null"
                    />
                    <input
                        v-model="form.email"
                        type="email"
                        placeholder="E-mail profissional"
                        class="rounded-md border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-custom-500"
                        @input="erroForm = null"
                    />
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tamanho in tamanhosTime"
                            :key="tamanho"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-xs"
                            :class="
                                form.tamanho === tamanho
                                    ? 'border-custom-500 bg-custom-500 text-white'
                                    : 'border-slate-200 text-slate-500'
                            "
                            @click="form.tamanho = tamanho"
                        >
                            {{ tamanho }}
                        </button>
                    </div>
                    <p v-if="erroForm" class="text-xs text-red-500">
                        {{ erroForm }}
                    </p>
                    <button
                        type="submit"
                        class="landing-btn landing-btn-primary"
                    >
                        Quero a demonstração
                    </button>
                </form>
            </div>
        </div>
    </section>

    <div
        v-if="toast"
        class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-full bg-custom-500 px-5 py-2.5 text-sm text-white shadow-lg"
    >
        {{ toast }}
    </div>
</template>
