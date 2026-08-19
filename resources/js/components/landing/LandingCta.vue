<script setup lang="ts">
import { onUnmounted, reactive, ref } from 'vue';
import { tamanhosTime } from '@/data/landing';

const form = reactive({
    escritorio: '',
    email: '',
    tamanho: '3 a 8 pessoas',
});

const enviado = ref(false);
const erroForm = ref<string | null>(null);
const toast = ref<string | null>(null);

let toastTimer: ReturnType<typeof setTimeout> | undefined;

function flash(texto: string) {
    clearTimeout(toastTimer);
    toast.value = texto;
    toastTimer = setTimeout(() => {
        toast.value = null;
    }, 2800);
}

function enviar() {
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
    <section
        id="demonstracao"
        class="mx-auto max-w-[1180px] px-7 pt-14 pb-[76px]"
    >
        <div
            class="grid items-center gap-10 rounded-2xl bg-[#14181E] px-7 py-12 sm:px-[46px] sm:py-12 lg:grid-cols-[1.05fr_0.95fr] lg:gap-[44px]"
        >
            <div class="flex flex-col gap-4">
                <h2
                    class="m-0 font-[family-name:var(--font-landing-display)] text-[32px] leading-[1.14] font-normal tracking-[-0.02em] text-[#FBF9F4] sm:text-[36px]"
                >
                    Veja o Antessala com os seus funis.
                </h2>
                <p class="m-0 text-[15px] leading-[1.7] text-[#A8AFB9]">
                    Demonstração de 30 minutos, conduzida por quem entende de
                    escritório — não por vendedor de software. Levamos seus dois
                    funis já montados.
                </p>
                <div class="flex flex-wrap gap-[22px] pt-1.5">
                    <span class="text-[12.5px] text-[#7C8593]">
                        Sem cartão de crédito
                    </span>
                    <span class="text-[12.5px] text-[#7C8593]">
                        Piloto de 30 dias
                    </span>
                </div>
            </div>

            <div
                v-if="enviado"
                class="flex flex-col gap-2 rounded-xl border border-[#2B313A] bg-white/5 p-[26px]"
            >
                <span
                    class="grid h-8 w-8 place-items-center rounded-full bg-[#14574F] text-[15px] text-[#FBF9F4]"
                >
                    ✓
                </span>
                <span
                    class="font-[family-name:var(--font-landing-display)] text-xl text-[#FBF9F4]"
                >
                    Pedido recebido
                </span>
                <span class="text-[13.5px] leading-[1.6] text-[#A8AFB9]">
                    Entramos em contato em até um dia útil no e-mail informado.
                    Nenhum dado foi usado para outra finalidade.
                </span>
            </div>

            <form
                v-else
                class="flex flex-col gap-3 rounded-xl border border-[#2B313A] bg-white/5 p-[22px]"
                @submit.prevent="enviar"
            >
                <label class="flex flex-col gap-[5px]">
                    <span
                        class="font-[family-name:var(--font-landing-mono)] text-[9.5px] tracking-[0.11em] text-[#7C8593] uppercase"
                    >
                        Escritório
                    </span>
                    <input
                        v-model="form.escritorio"
                        type="text"
                        placeholder="ASF Advogados Associados"
                        class="rounded-lg border border-[#2B313A] bg-[#191E25] px-3 py-[11px] text-[13.5px] text-[#FBF9F4] outline-none placeholder:text-[#A9A296] focus:border-[#C79A4E]"
                        @input="erroForm = null"
                    />
                </label>
                <label class="flex flex-col gap-[5px]">
                    <span
                        class="font-[family-name:var(--font-landing-mono)] text-[9.5px] tracking-[0.11em] text-[#7C8593] uppercase"
                    >
                        E-mail profissional
                    </span>
                    <input
                        v-model="form.email"
                        type="email"
                        placeholder="socio@escritorio.adv.br"
                        class="rounded-lg border border-[#2B313A] bg-[#191E25] px-3 py-[11px] text-[13.5px] text-[#FBF9F4] outline-none placeholder:text-[#A9A296] focus:border-[#C79A4E]"
                        @input="erroForm = null"
                    />
                </label>
                <span
                    class="font-[family-name:var(--font-landing-mono)] text-[9.5px] tracking-[0.11em] text-[#7C8593] uppercase"
                >
                    Tamanho do time comercial
                </span>
                <div class="flex flex-wrap gap-[7px]">
                    <button
                        v-for="tamanho in tamanhosTime"
                        :key="tamanho"
                        type="button"
                        class="cursor-pointer rounded-full border px-[13px] py-2 text-[12.5px]"
                        :class="
                            form.tamanho === tamanho
                                ? 'border-[#C79A4E] bg-[#C79A4E] text-[#14181E]'
                                : 'border-[#2B313A] bg-transparent text-[#A8AFB9]'
                        "
                        @click="form.tamanho = tamanho"
                    >
                        {{ tamanho }}
                    </button>
                </div>
                <span v-if="erroForm" class="text-xs text-[#E3A79C]">
                    {{ erroForm }}
                </span>
                <button
                    type="submit"
                    class="mt-1 cursor-pointer rounded-[9px] border border-[#C79A4E] bg-[#C79A4E] py-3 text-sm font-medium text-[#14181E] transition-colors hover:bg-[#d4a85c]"
                >
                    Quero a demonstração
                </button>
                <span class="text-[11px] leading-normal text-[#7C8593]">
                    Usamos seus dados apenas para agendar a conversa, na base
                    legal de procedimentos preliminares ao contrato. Você pode
                    pedir a exclusão quando quiser.
                </span>
            </form>
        </div>
    </section>

    <div
        v-if="toast"
        class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2 rounded-full bg-[#171B21] px-[18px] py-[11px] text-[12.5px] text-[#FBF9F4] shadow-[0_8px_24px_rgba(23,27,33,0.22)]"
    >
        {{ toast }}
    </div>
</template>
