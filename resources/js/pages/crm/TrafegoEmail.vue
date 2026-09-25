<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    destroy,
    store,
    testarConexao,
    update,
} from '@/actions/App/Http/Controllers/TrafegoEmailController';

type ContaEmail = {
    id: number;
    nome: string;
    host: string;
    port: number;
    encryption: string;
    username: string;
    senha_definida: boolean;
    pasta: string;
    funil_id: number;
    funil_nome: string | null;
    finalidade_consentimento_slug: string;
    ativo: boolean;
    ultima_captura_em: string | null;
    ultimo_status: string | null;
    ultimo_erro: string | null;
    atualizado_por: string | null;
};

type Opcao = { id?: number; slug?: string; nome: string };

const props = defineProps<{
    contas: ContaEmail[];
    funis: Opcao[];
    finalidades: Opcao[];
}>();

const fieldClass =
    'rounded-lg border border-border bg-card px-2.5 py-1.5 text-[12.5px] text-foreground outline-none focus:border-primary';

const contaEdicao = ref<number | null>(null);
const modoNovaConta = ref(props.contas.length === 0);
const mostrarSenha = ref(false);

const form = useForm({
    nome: '',
    host: 'imap.gmail.com',
    port: 993,
    encryption: 'ssl',
    username: '',
    password: '',
    pasta: 'INBOX',
    funil_id: props.funis[0]?.id ?? null,
    finalidade_consentimento_slug: props.finalidades[0]?.slug ?? '',
    ativo: true,
});

const contaAtual = computed<ContaEmail | null>(
    () => props.contas.find((c) => c.id === contaEdicao.value) ?? null,
);

function abrirNova(): void {
    modoNovaConta.value = true;
    contaEdicao.value = null;
    form.reset();
    form.clearErrors();
    mostrarSenha.value = false;
}

function editar(conta: ContaEmail): void {
    modoNovaConta.value = false;
    contaEdicao.value = conta.id;
    form.reset();
    form.nome = conta.nome;
    form.host = conta.host;
    form.port = conta.port;
    form.encryption = conta.encryption;
    form.username = conta.username;
    form.password = '';
    form.pasta = conta.pasta;
    form.funil_id = conta.funil_id;
    form.finalidade_consentimento_slug = conta.finalidade_consentimento_slug;
    form.ativo = conta.ativo;
    form.clearErrors();
    mostrarSenha.value = false;
}

function salvarConta(): void {
    if (contaAtual.value) {
        form.patch(update.url({ conta: contaAtual.value.id }), { preserveScroll: true });
        return;
    }
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            modoNovaConta.value = false;
            form.reset();
        },
    });
}

function removerConta(conta: ContaEmail): void {
    if (!confirm(`Remover a caixa de e-mail "${conta.nome}"?`)) {
        return;
    }
    router.delete(destroy.url({ conta: conta.id }), { preserveScroll: true });
}

function testar(conta: ContaEmail): void {
    router.post(testarConexao.url({ conta: conta.id }), {}, { preserveScroll: true });
}

function statusBadge(conta: ContaEmail): string {
    if (conta.ultimo_status === 'ok') return '✓ conexão OK';
    if (conta.ultimo_status === 'erro') return `✗ ${conta.ultimo_erro ?? 'erro'}`;
    return 'ainda não testada';
}
</script>

<template>
    <div class="flex max-w-[900px] flex-col gap-4">
        <p class="m-0 text-[12.5px] text-muted-foreground">
            Caixas de e-mail (IMAP) monitoradas para captura automática de leads. Cada caixa
            define o funil e a base legal de consentimento dos leads que ela captura.
            No Google Workspace use
            <strong class="font-medium text-foreground">senha de app</strong>
            (Conta Google → Segurança → Senhas de app), não a senha normal da conta —
            host <code class="font-[family-name:var(--font-crm-mono)] text-[11px]">imap.gmail.com</code>,
            porta <code class="font-[family-name:var(--font-crm-mono)] text-[11px]">993</code>, SSL.
        </p>

        <p
            v-if="props.contas.length === 0"
            class="m-0 rounded-[10px] border border-border bg-card px-[17px] py-4 text-[12.5px] text-muted-foreground"
        >
            Nenhuma caixa de e-mail cadastrada. Adicione uma abaixo para começar a capturar leads.
        </p>

        <div class="flex flex-col gap-3 rounded-[10px] border border-border bg-card px-5 py-4">
            <div class="flex items-center justify-between">
                <h2 class="m-0 font-[family-name:var(--font-crm-display)] text-[16px] font-medium">
                    Caixas de e-mail
                </h2>
                <button
                    type="button"
                    class="cursor-pointer rounded-lg border border-dashed border-[#C7C1B4] px-3 py-1.5 text-[12px] text-muted-foreground hover:border-primary"
                    @click="abrirNova"
                >
                    + Nova caixa
                </button>
            </div>

            <div
                v-for="c in props.contas"
                :key="c.id"
                class="flex items-center justify-between gap-3 rounded-lg border border-[#F2EFE8] px-3 py-2 text-[12.5px]"
            >
                <div>
                    <span class="font-medium text-foreground">{{ c.nome }}</span>
                    <span class="ml-2 font-[family-name:var(--font-crm-mono)] text-[11px] text-muted-foreground">
                        {{ c.username }} · funil: {{ c.funil_nome ?? '—' }}
                    </span>
                    <span
                        class="ml-2 text-[11px]"
                        :class="c.ultimo_status === 'erro' ? 'text-[#9B3B2F]' : 'text-accent'"
                    >
                        {{ statusBadge(c) }}
                    </span>
                    <span v-if="!c.ativo" class="ml-2 rounded-full bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground">
                        inativa
                    </span>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button type="button" class="cursor-pointer text-[11.5px] text-muted-foreground underline" @click="testar(c)">Testar conexão</button>
                    <button type="button" class="cursor-pointer text-[11.5px] text-muted-foreground underline" @click="editar(c)">Editar</button>
                    <button type="button" class="cursor-pointer text-[11.5px] text-[#9B3B2F] underline" @click="removerConta(c)">Remover</button>
                </div>
            </div>

            <form
                v-if="modoNovaConta || contaAtual"
                class="grid gap-2 rounded-lg border border-border bg-card px-3 py-3 sm:grid-cols-2"
                @submit.prevent="salvarConta"
            >
                <label class="flex flex-col gap-1 text-[11px] text-muted-foreground">
                    Nome
                    <input v-model="form.nome" type="text" required :class="fieldClass" />
                    <span v-if="form.errors.nome" class="text-[#9B3B2F]">{{ form.errors.nome }}</span>
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-muted-foreground">
                    E-mail (usuário IMAP)
                    <input v-model="form.username" type="email" required :class="fieldClass" />
                    <span v-if="form.errors.username" class="text-[#9B3B2F]">{{ form.errors.username }}</span>
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-muted-foreground">
                    Senha de app
                    <div class="flex gap-1.5">
                        <input
                            v-model="form.password"
                            :type="mostrarSenha ? 'text' : 'password'"
                            autocomplete="off"
                            :required="!contaAtual"
                            :placeholder="contaAtual ? (contaAtual.senha_definida ? '•••••••• — em branco mantém' : 'definir senha') : ''"
                            class="min-w-0 flex-1 rounded-lg border border-border bg-card px-2.5 py-1.5 text-[12px]"
                        />
                        <button type="button" class="cursor-pointer rounded-lg border border-border bg-card px-2 text-[11px]" @click="mostrarSenha = !mostrarSenha">
                            {{ mostrarSenha ? 'Ocultar' : 'Ver' }}
                        </button>
                    </div>
                    <span v-if="form.errors.password" class="text-[#9B3B2F]">{{ form.errors.password }}</span>
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-muted-foreground">
                    Host / porta / criptografia
                    <div class="flex gap-1.5">
                        <input v-model="form.host" type="text" required class="min-w-0 flex-1" :class="fieldClass" />
                        <input v-model.number="form.port" type="number" required class="w-20" :class="fieldClass" />
                        <select v-model="form.encryption" :class="fieldClass">
                            <option value="ssl">ssl</option>
                            <option value="tls">tls</option>
                            <option value="notls">notls</option>
                        </select>
                    </div>
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-muted-foreground">
                    Pasta IMAP
                    <input v-model="form.pasta" type="text" required :class="fieldClass" />
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-muted-foreground">
                    Funil de destino
                    <select v-model.number="form.funil_id" required :class="fieldClass">
                        <option v-for="f in props.funis" :key="f.id" :value="f.id">{{ f.nome }}</option>
                    </select>
                    <span v-if="form.errors.funil_id" class="text-[#9B3B2F]">{{ form.errors.funil_id }}</span>
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-muted-foreground">
                    Finalidade do consentimento (LGPD)
                    <select v-model="form.finalidade_consentimento_slug" required :class="fieldClass">
                        <option v-for="f in props.finalidades" :key="f.slug" :value="f.slug">{{ f.nome }}</option>
                    </select>
                    <span v-if="form.errors.finalidade_consentimento_slug" class="text-[#9B3B2F]">{{ form.errors.finalidade_consentimento_slug }}</span>
                </label>
                <label class="flex items-center gap-2 text-[11px] text-muted-foreground">
                    <input v-model="form.ativo" type="checkbox" />
                    Ativa
                </label>

                <div class="col-span-full flex items-center gap-2">
                    <button
                        type="submit"
                        class="cursor-pointer rounded-[7px] border border-primary bg-primary px-3.5 py-2 text-[12.5px] text-primary-foreground"
                        :disabled="form.processing"
                    >
                        Salvar
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer text-[12px] text-muted-foreground underline"
                        @click="modoNovaConta = false; contaEdicao = null"
                    >
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
