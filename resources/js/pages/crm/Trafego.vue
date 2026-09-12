<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import TrafegoTabs from '@/components/crm/TrafegoTabs.vue';
import {
    destroy,
    eventoTeste,
    gerarTokenLead,
    store,
    testarConexao,
    update,
} from '@/actions/App/Http/Controllers/TrafegoController';

type Campanha = {
    id: number;
    nome_campanha: string;
    slug: string;
    pixel_id: string;
    token_mascarado: string;
    token_definido: boolean;
    token_verificado_em: string | null;
    token_valido: boolean | null;
    test_event_definido: boolean;
    api_version: string;
    action_source: string;
    origem_url: string | null;
    finalidade_consentimento_slug: string | null;
    ativo: boolean;
    ultimo_evento_em: string | null;
    ultimo_status: string | null;
    atualizado_por: string | null;
};

type Kpi = { label: string; value: string };
type Finalidade = { slug: string; nome: string };

type Saude = {
    erros_24h: number;
    fila_pendente: number;
    campanhas: { nome: string; ultimo_evento_em: string | null }[];
};

type CaptacaoSite = {
    endpoint: string;
    token_mascara: string;
    token_gerado_em: string | null;
};

const props = defineProps<{
    campanhas: Campanha[];
    kpis: Kpi[];
    finalidades: Finalidade[];
    saude: Saude;
    captacaoSite: CaptacaoSite;
}>();

const tokenCaptacaoRevelado = ref<string | null>(null);

function aoReceberFlash(event: Event): void {
    const valor = (event as CustomEvent).detail?.flash?.tokenCaptacaoSite;

    if (typeof valor === 'string') {
        tokenCaptacaoRevelado.value = valor;
    }
}

let pararDeOuvirFlash: (() => void) | null = null;

onMounted(() => {
    pararDeOuvirFlash = router.on('flash', aoReceberFlash);
});

onUnmounted(() => {
    pararDeOuvirFlash?.();
});

function regenerarTokenCaptacao(): void {
    const jaExiste = props.captacaoSite.token_gerado_em !== null;

    if (
        jaExiste &&
        !window.confirm(
            'Gerar um novo token invalida o atual — o site precisará ser atualizado. Continuar?',
        )
    ) {
        return;
    }

    router.post(gerarTokenLead.url(), {}, { preserveScroll: true });
}

function idade(iso: string | null): string {
    if (!iso) {
        return 'nunca';
    }
    const horas = Math.round((Date.now() - new Date(iso).getTime()) / 3_600_000);
    if (horas < 1) {
        return 'há minutos';
    }
    return horas < 48 ? `há ${horas}h` : `há ${Math.round(horas / 24)}d`;
}

const ACTION_SOURCES = [
    'website',
    'system_generated',
    'app',
    'chat',
    'email',
    'phone_call',
    'other',
] as const;

const API_VERSIONS = ['v21.0', 'v20.0', 'v19.0', 'v18.0'] as const;

const selecionadaId = ref<number | null>(props.campanhas[0]?.id ?? null);
const modoNova = ref(props.campanhas.length === 0);
const mostrarToken = ref(false);

const form = useForm({
    nome_campanha: '',
    slug: '',
    pixel_id: '',
    access_token: '',
    api_version: 'v21.0',
    action_source: 'system_generated',
    origem_url: '',
    finalidade_consentimento_slug: '',
    test_event_code: '',
    ativo: true,
});

const campanhaAtual = computed<Campanha | null>(
    () =>
        props.campanhas.find((c) => c.id === selecionadaId.value) ?? null,
);

const editando = computed(() => !modoNova.value && campanhaAtual.value !== null);

const endpointPreview = computed(
    () =>
        `https://graph.facebook.com/${form.api_version || 'v21.0'}/${
            form.pixel_id || '{pixel_id}'
        }/events`,
);

function preencher(campanha: Campanha | null): void {
    form.clearErrors();
    mostrarToken.value = false;
    form.access_token = '';

    if (campanha === null) {
        form.nome_campanha = '';
        form.slug = '';
        form.pixel_id = '';
        form.api_version = 'v21.0';
        form.action_source = 'system_generated';
        form.origem_url = '';
        form.finalidade_consentimento_slug = '';
        form.test_event_code = '';
        form.ativo = true;
        return;
    }

    form.nome_campanha = campanha.nome_campanha;
    form.slug = campanha.slug;
    form.pixel_id = campanha.pixel_id;
    form.api_version = campanha.api_version;
    form.action_source = campanha.action_source;
    form.origem_url = campanha.origem_url ?? '';
    form.finalidade_consentimento_slug =
        campanha.finalidade_consentimento_slug ?? '';
    form.test_event_code = '';
    form.ativo = campanha.ativo;
}

watch(
    [campanhaAtual, modoNova],
    () => {
        preencher(modoNova.value ? null : campanhaAtual.value);
    },
    { immediate: true },
);

// Mantém a seleção coerente quando a lista muda (após criar/excluir).
watch(
    () => props.campanhas,
    (lista) => {
        if (modoNova.value) {
            return;
        }
        if (!lista.some((c) => c.id === selecionadaId.value)) {
            selecionadaId.value = lista[0]?.id ?? null;
            modoNova.value = lista.length === 0;
        }
    },
);

function selecionar(id: number): void {
    modoNova.value = false;
    selecionadaId.value = id;
}

function novaCampanha(): void {
    modoNova.value = true;
}

function nomeToSlug(): void {
    if (!editando.value && form.slug === '') {
        form.slug = form.nome_campanha
            .normalize('NFD')
            .replace(/\p{Diacritic}/gu, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }
}

function salvar(): void {
    if (editando.value && campanhaAtual.value) {
        form.patch(update.url({ config: campanhaAtual.value.id }), {
            preserveScroll: true,
        });
        return;
    }

    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            modoNova.value = false;
        },
    });
}

function testar(): void {
    if (!campanhaAtual.value) {
        return;
    }

    router.post(
        testarConexao.url({ config: campanhaAtual.value.id }),
        { access_token: form.access_token || undefined },
        { preserveScroll: true },
    );
}

function enviarEventoTeste(): void {
    if (!campanhaAtual.value) {
        return;
    }

    router.post(
        eventoTeste.url({ config: campanhaAtual.value.id }),
        { test_event_code: form.test_event_code || undefined },
        { preserveScroll: true },
    );
}

function remover(): void {
    if (!campanhaAtual.value) {
        return;
    }

    const acao = campanhaAtual.value.ultimo_evento_em
        ? 'desativar'
        : 'excluir';

    if (!confirm(`Deseja ${acao} a campanha “${campanhaAtual.value.nome_campanha}”?`)) {
        return;
    }

    router.delete(destroy.url({ config: campanhaAtual.value.id }), {
        preserveScroll: true,
    });
}

function statusTokenLabel(campanha: Campanha): string {
    if (campanha.token_valido === true) {
        return '✓ token válido';
    }
    if (campanha.token_valido === false) {
        return '✗ token inválido';
    }
    return 'token não testado';
}

const dataFmt = new Intl.DateTimeFormat('pt-BR', {
    dateStyle: 'short',
    timeStyle: 'short',
});

function formatarData(iso: string | null): string {
    return iso ? dataFmt.format(new Date(iso)) : '—';
}
</script>

<template>
    <div class="flex max-w-[1180px] flex-col gap-4">
        <TrafegoTabs />

        <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="k in props.kpis"
                :key="k.label"
                class="flex flex-col gap-1.5 rounded-[10px] border border-border bg-card px-[17px] py-[15px]"
            >
                <span
                    class="font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-muted-foreground uppercase"
                >
                    {{ k.label }}
                </span>
                <span
                    class="font-[family-name:var(--font-crm-display)] text-[26px] leading-none font-medium"
                >
                    {{ k.value }}
                </span>
            </div>
        </div>

        <div
            class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-[10px] border border-border bg-card px-[17px] py-3 text-[11.5px] text-muted-foreground"
        >
            <span class="font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-muted-foreground uppercase">
                Saúde
            </span>
            <span :class="props.saude.erros_24h > 0 ? 'text-[#9B3B2F]' : ''">
                {{ props.saude.erros_24h }} erro(s) em 24h
            </span>
            <span>fila meta-capi: {{ props.saude.fila_pendente }} pendente(s)</span>
            <span
                v-for="c in props.saude.campanhas"
                :key="c.nome"
                class="text-muted-foreground"
            >
                {{ c.nome }} — último envio {{ idade(c.ultimo_evento_em) }}
            </span>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_1.1fr]">
            <div class="flex flex-col gap-3">
                <button
                    type="button"
                    class="cursor-pointer rounded-[10px] border border-dashed border-[#C7C1B4] bg-card px-[17px] py-3 text-left text-[13px] font-medium text-muted-foreground hover:border-primary hover:text-foreground"
                    :class="modoNova ? 'border-primary text-foreground' : ''"
                    @click="novaCampanha"
                >
                    + Nova campanha
                </button>

                <button
                    v-for="c in props.campanhas"
                    :key="c.id"
                    type="button"
                    class="cursor-pointer rounded-[10px] border px-[17px] py-[15px] text-left"
                    :class="
                        !modoNova && selecionadaId === c.id
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'border-border bg-card text-foreground hover:bg-card'
                    "
                    @click="selecionar(c.id)"
                >
                    <div class="flex items-start justify-between gap-2">
                        <span class="text-[13.5px] font-medium">{{
                            c.nome_campanha
                        }}</span>
                        <span
                            class="rounded-full px-2 py-0.5 text-[10.5px]"
                            :class="
                                c.ativo
                                    ? !modoNova && selecionadaId === c.id
                                        ? 'bg-card/15 text-primary'
                                        : 'bg-accent/15 text-accent'
                                    : 'bg-destructive/15 text-[#9B3B2F]'
                            "
                        >
                            {{ c.ativo ? 'ativa' : 'inativa' }}
                        </span>
                    </div>
                    <p
                        class="mt-1.5 mb-0 font-[family-name:var(--font-crm-mono)] text-[11px]"
                        :class="
                            !modoNova && selecionadaId === c.id
                                ? 'text-[#B9BEC7]'
                                : 'text-muted-foreground'
                        "
                    >
                        pixel {{ c.pixel_id }} · {{ statusTokenLabel(c) }}
                    </p>
                </button>

                <p
                    v-if="props.campanhas.length === 0 && !modoNova"
                    class="m-0 rounded-[10px] border border-border bg-card px-[17px] py-4 text-[12.5px] text-muted-foreground"
                >
                    Nenhuma campanha cadastrada.
                    <button
                        type="button"
                        class="cursor-pointer font-medium text-foreground underline"
                        @click="novaCampanha"
                    >
                        Cadastrar a primeira campanha
                    </button>
                </p>
            </div>

            <div
                class="flex flex-col gap-3.5 rounded-[10px] border border-border bg-card px-5 pt-[18px] pb-5"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2
                            class="m-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                        >
                            {{
                                editando
                                    ? campanhaAtual?.nome_campanha
                                    : 'Nova campanha'
                            }}
                        </h2>
                        <p
                            v-if="editando && campanhaAtual?.atualizado_por"
                            class="mt-1 mb-0 text-xs text-muted-foreground"
                        >
                            Última alteração por
                            {{ campanhaAtual.atualizado_por }}
                        </p>
                    </div>
                    <button
                        v-if="editando"
                        type="button"
                        class="cursor-pointer rounded-lg border border-border bg-card px-3 py-1.5 text-xs text-[#9B3B2F] hover:border-[#9B3B2F]"
                        @click="remover"
                    >
                        {{
                            campanhaAtual?.ultimo_evento_em
                                ? 'Desativar'
                                : 'Excluir'
                        }}
                    </button>
                </div>

                <form class="flex flex-col gap-3" @submit.prevent="salvar">
                    <label class="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                        Nome da campanha
                        <input
                            v-model="form.nome_campanha"
                            type="text"
                            required
                            class="rounded-lg border border-border bg-card px-3 py-2 text-[13px] text-foreground outline-none focus:border-primary"
                            @blur="nomeToSlug"
                        />
                        <span v-if="form.errors.nome_campanha" class="text-[#9B3B2F]">{{
                            form.errors.nome_campanha
                        }}</span>
                    </label>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex flex-col gap-1 text-[11.5px] text-muted-foreground"
                        >
                            Slug
                            <input
                                v-model="form.slug"
                                type="text"
                                required
                                class="rounded-lg border border-border bg-card px-3 py-2 font-[family-name:var(--font-crm-mono)] text-[12.5px] text-foreground outline-none focus:border-primary"
                            />
                            <span v-if="form.errors.slug" class="text-[#9B3B2F]">{{
                                form.errors.slug
                            }}</span>
                        </label>
                        <label
                            class="flex flex-col gap-1 text-[11.5px] text-muted-foreground"
                        >
                            Pixel / Dataset ID
                            <input
                                v-model="form.pixel_id"
                                type="text"
                                inputmode="numeric"
                                required
                                class="rounded-lg border border-border bg-card px-3 py-2 font-[family-name:var(--font-crm-mono)] text-[12.5px] text-foreground outline-none focus:border-primary"
                            />
                            <span v-if="form.errors.pixel_id" class="text-[#9B3B2F]">{{
                                form.errors.pixel_id
                            }}</span>
                        </label>
                    </div>

                    <label class="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                        Access token
                        <div class="flex items-center gap-2">
                            <input
                                v-model="form.access_token"
                                :type="mostrarToken ? 'text' : 'password'"
                                autocomplete="off"
                                :required="!editando"
                                :placeholder="
                                    editando
                                        ? `${campanhaAtual?.token_mascarado} — deixe em branco para manter`
                                        : 'EAA…'
                                "
                                class="min-w-0 flex-1 rounded-lg border border-border bg-card px-3 py-2 font-[family-name:var(--font-crm-mono)] text-[12.5px] text-foreground outline-none focus:border-primary"
                            />
                            <button
                                type="button"
                                class="cursor-pointer rounded-lg border border-border bg-card px-3 py-2 text-xs text-muted-foreground"
                                @click="mostrarToken = !mostrarToken"
                            >
                                {{ mostrarToken ? 'Ocultar' : 'Mostrar' }}
                            </button>
                        </div>
                        <span v-if="form.errors.access_token" class="text-[#9B3B2F]">{{
                            form.errors.access_token
                        }}</span>
                    </label>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex flex-col gap-1 text-[11.5px] text-muted-foreground"
                        >
                            Versão da API
                            <select
                                v-model="form.api_version"
                                class="rounded-lg border border-border bg-card px-3 py-2 text-[13px] text-foreground"
                            >
                                <option
                                    v-for="v in API_VERSIONS"
                                    :key="v"
                                    :value="v"
                                >
                                    {{ v }}
                                </option>
                            </select>
                        </label>
                        <label
                            class="flex flex-col gap-1 text-[11.5px] text-muted-foreground"
                        >
                            Action source
                            <select
                                v-model="form.action_source"
                                class="rounded-lg border border-border bg-card px-3 py-2 text-[13px] text-foreground"
                            >
                                <option
                                    v-for="a in ACTION_SOURCES"
                                    :key="a"
                                    :value="a"
                                >
                                    {{ a }}
                                </option>
                            </select>
                        </label>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label
                            class="flex flex-col gap-1 text-[11.5px] text-muted-foreground"
                        >
                            URL de origem
                            <input
                                v-model="form.origem_url"
                                type="url"
                                placeholder="https://www.instagram.com/…"
                                class="rounded-lg border border-border bg-card px-3 py-2 text-[12.5px] text-foreground outline-none focus:border-primary"
                            />
                            <span v-if="form.errors.origem_url" class="text-[#9B3B2F]">{{
                                form.errors.origem_url
                            }}</span>
                        </label>
                        <label
                            class="flex flex-col gap-1 text-[11.5px] text-muted-foreground"
                        >
                            Finalidade de consentimento
                            <select
                                v-model="form.finalidade_consentimento_slug"
                                class="rounded-lg border border-border bg-card px-3 py-2 text-[13px] text-foreground"
                            >
                                <option value="">— nenhuma (bloqueia envio) —</option>
                                <option
                                    v-for="f in props.finalidades"
                                    :key="f.slug"
                                    :value="f.slug"
                                >
                                    {{ f.nome }}
                                </option>
                            </select>
                            <span
                                v-if="form.errors.finalidade_consentimento_slug"
                                class="text-[#9B3B2F]"
                                >{{ form.errors.finalidade_consentimento_slug }}</span
                            >
                        </label>
                    </div>

                    <label class="flex flex-col gap-1 text-[11.5px] text-muted-foreground">
                        Test event code
                        <input
                            v-model="form.test_event_code"
                            type="text"
                            placeholder="TEST12345"
                            class="rounded-lg border border-border bg-card px-3 py-2 font-[family-name:var(--font-crm-mono)] text-[12.5px] text-foreground outline-none focus:border-primary"
                        />
                        <span v-if="form.errors.test_event_code" class="text-[#9B3B2F]">{{
                            form.errors.test_event_code
                        }}</span>
                    </label>

                    <button
                        type="button"
                        class="flex cursor-pointer items-center justify-between gap-3 border-0 bg-transparent p-0 text-left"
                        @click="form.ativo = !form.ativo"
                    >
                        <div>
                            <div class="text-[13px] text-foreground">
                                Campanha ativa
                            </div>
                            <div
                                class="text-[11.5px]"
                                :style="{
                                    color: form.ativo ? 'var(--accent)' : '#9B3B2F',
                                }"
                            >
                                {{
                                    form.ativo
                                        ? 'recebe e envia eventos do CRM'
                                        : 'desligada — nenhum evento é enviado'
                                }}
                            </div>
                        </div>
                        <span
                            class="relative block h-[18px] w-8 shrink-0 rounded-full"
                            :style="{
                                background: form.ativo ? 'var(--accent)' : '#D6D1C6',
                            }"
                        >
                            <span
                                class="absolute top-0.5 h-3.5 w-3.5 rounded-full bg-card"
                                :style="{ left: form.ativo ? '16px' : '2px' }"
                            />
                        </span>
                    </button>

                    <code
                        class="rounded-lg border border-border bg-card px-3 py-2.5 font-[family-name:var(--font-crm-mono)] text-[11px] break-all text-muted-foreground"
                    >
                        {{ endpointPreview }}
                    </code>

                    <div class="flex flex-wrap gap-2 pt-1">
                        <button
                            type="submit"
                            class="cursor-pointer rounded-[7px] border border-primary bg-primary px-3.5 py-2 text-[12.5px] text-primary-foreground disabled:opacity-60"
                            :disabled="form.processing"
                        >
                            Salvar
                        </button>
                        <button
                            v-if="editando"
                            type="button"
                            class="cursor-pointer rounded-[7px] border border-border bg-card px-3.5 py-2 text-[12.5px] text-muted-foreground hover:border-primary"
                            @click="testar"
                        >
                            Testar conexão
                        </button>
                        <button
                            v-if="editando"
                            type="button"
                            class="cursor-pointer rounded-[7px] border border-border bg-card px-3.5 py-2 text-[12.5px] text-muted-foreground hover:border-primary disabled:opacity-50"
                            :disabled="
                                !form.test_event_code &&
                                !campanhaAtual?.test_event_definido
                            "
                            @click="enviarEventoTeste"
                        >
                            Enviar evento de teste
                        </button>
                    </div>
                </form>

                <div
                    v-if="editando && campanhaAtual"
                    class="mt-1 grid gap-2 rounded-lg border border-[#F2EFE8] bg-card px-3.5 py-3 text-[11.5px] text-muted-foreground sm:grid-cols-3"
                >
                    <div class="flex flex-col gap-0.5">
                        <span class="text-muted-foreground">Último evento</span>
                        <span>{{ formatarData(campanhaAtual.ultimo_evento_em) }}</span>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-muted-foreground">Último status</span>
                        <span
                            class="w-fit rounded-full px-2 py-0.5"
                            :class="
                                campanhaAtual.ultimo_status === 'ok'
                                    ? 'bg-accent/15 text-accent'
                                    : campanhaAtual.ultimo_status
                                      ? 'bg-destructive/15 text-[#9B3B2F]'
                                      : 'bg-muted text-muted-foreground'
                            "
                            >{{ campanhaAtual.ultimo_status ?? '—' }}</span
                        >
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-muted-foreground">Token verificado</span>
                        <span
                            >{{ formatarData(campanhaAtual.token_verificado_em) }} ·
                            {{ statusTokenLabel(campanhaAtual) }}</span
                        >
                    </div>
                </div>
            </div>
        </div>

        <div
            class="flex flex-col gap-3 rounded-[10px] border border-border bg-card px-[17px] py-[15px]"
        >
            <div class="flex flex-col gap-1">
                <span
                    class="font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-muted-foreground uppercase"
                >
                    Captação de leads pelo site
                </span>
                <p class="m-0 text-[12px] text-muted-foreground">
                    O site/landing envia o lead (com os parâmetros do Pixel) para
                    este endpoint. É o que permite deduplicar navegador × CAPI e
                    atribuir o anúncio.
                </p>
            </div>

            <code
                class="rounded-lg border border-border bg-card px-3 py-2.5 font-[family-name:var(--font-crm-mono)] text-[11px] break-all text-muted-foreground"
            >
                POST {{ props.captacaoSite.endpoint }}
            </code>

            <div class="flex flex-wrap items-center gap-2 text-[12px] text-muted-foreground">
                <span class="text-muted-foreground">Token:</span>
                <span class="font-[family-name:var(--font-crm-mono)]">
                    {{ props.captacaoSite.token_mascara }}
                </span>
                <span
                    v-if="props.captacaoSite.token_gerado_em"
                    class="text-muted-foreground"
                >
                    · gerado {{ formatarData(props.captacaoSite.token_gerado_em) }}
                </span>
                <span v-else class="text-primary">· ainda não gerado</span>
                <button
                    type="button"
                    class="cursor-pointer rounded-[7px] border border-border bg-card px-3 py-1.5 text-[12px] text-muted-foreground hover:border-primary"
                    @click="regenerarTokenCaptacao"
                >
                    {{ props.captacaoSite.token_gerado_em ? 'Gerar novo token' : 'Gerar token' }}
                </button>
            </div>

            <div
                v-if="tokenCaptacaoRevelado"
                class="flex flex-col gap-1.5 rounded-lg border border-[#E8D9B8] bg-[#FBF7EF] px-3.5 py-3"
            >
                <span
                    class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.1em] text-primary uppercase"
                >
                    Copie agora — não será mostrado de novo
                </span>
                <code
                    class="font-[family-name:var(--font-crm-mono)] text-[12px] break-all text-foreground"
                >
                    {{ tokenCaptacaoRevelado }}
                </code>
                <span class="text-[11px] text-muted-foreground">
                    Envie em cada request no header
                    <span class="font-[family-name:var(--font-crm-mono)]"
                        >Authorization: Bearer &lt;token&gt;</span
                    >.
                </span>
            </div>
        </div>
    </div>
</template>
