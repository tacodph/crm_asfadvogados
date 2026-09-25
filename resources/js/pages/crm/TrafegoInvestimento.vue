<script setup lang="ts">
import { Deferred, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import {
    destroyConta,
    index,
    sincronizar,
    storeConta,
    testarConexao,
    updateConta,
} from '@/actions/App/Http/Controllers/TrafegoInvestimentoController';
import type {
    ContaAnuncio,
    ContaStatusAoVivo,
    InvestimentoFiltros,
    InvestimentoKpi,
    InvestimentoLinha,
    InvestimentoOpcoes,
    InvestimentoSeriePonto,
} from '@/types/trafego-ads';

const props = defineProps<{
    contas: ContaAnuncio[];
    kpis: InvestimentoKpi[];
    linhas: InvestimentoLinha[];
    serie: InvestimentoSeriePonto[];
    filtros: InvestimentoFiltros;
    opcoes: InvestimentoOpcoes;
    saude?: ContaStatusAoVivo;
}>();

const filtros = reactive({ ...props.filtros });
const contaEdicao = ref<number | null>(null);
const modoNovaConta = ref(props.contas.length === 0);
const mostrarToken = ref(false);

const fieldClass =
    'rounded-lg border border-border bg-card px-2.5 py-1.5 text-[12.5px] text-foreground outline-none focus:border-primary';

function brl(centavos: number): string {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(
        centavos / 100,
    );
}

function aplicar(extra: Partial<InvestimentoFiltros> = {}): void {
    router.get(
        index.url(),
        { ...filtros, ...extra, campanha: extra.campanha ?? null, conjunto: extra.conjunto ?? null },
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function drill(linha: InvestimentoLinha): void {
    if (filtros.nivel === 'campanha') {
        aplicar({ campanha: linha.objeto_id, nivel: 'conjunto' });
    } else if (filtros.nivel === 'conjunto') {
        aplicar({ conjunto: linha.objeto_id, nivel: 'anuncio' });
    }
}

function limparDrill(): void {
    filtros.campanha = null;
    filtros.conjunto = null;
    filtros.nivel = 'campanha';
    aplicar();
}

function sincronizarAgora(): void {
    router.post(sincronizar.url(), {}, { preserveScroll: true });
}

const serieMax = computed(() =>
    Math.max(1, ...props.serie.map((p) => p.investido_centavos)),
);

// ---- CRUD de conta ----
const form = useForm({
    nome: '',
    ad_account_id: '',
    business_id: '',
    access_token: '',
});

const contaAtual = computed<ContaAnuncio | null>(
    () => props.contas.find((c) => c.id === contaEdicao.value) ?? null,
);

function abrirNova(): void {
    modoNovaConta.value = true;
    contaEdicao.value = null;
    form.reset();
    form.clearErrors();
    mostrarToken.value = false;
}

function editar(conta: ContaAnuncio): void {
    modoNovaConta.value = false;
    contaEdicao.value = conta.id;
    form.reset();
    form.nome = conta.nome;
    form.ad_account_id = conta.ad_account_id;
    form.business_id = conta.business_id ?? '';
    form.clearErrors();
    mostrarToken.value = false;
}

function salvarConta(): void {
    if (contaAtual.value) {
        form.patch(updateConta.url({ conta: contaAtual.value.id }), { preserveScroll: true });
        return;
    }
    form.post(storeConta.url(), {
        preserveScroll: true,
        onSuccess: () => {
            modoNovaConta.value = false;
            form.reset();
        },
    });
}

function removerConta(conta: ContaAnuncio): void {
    if (!confirm(`Remover / desativar a conta "${conta.nome}"?`)) {
        return;
    }
    router.delete(destroyConta.url({ conta: conta.id }), { preserveScroll: true });
}

function testar(conta: ContaAnuncio): void {
    router.post(testarConexao.url({ conta: conta.id }), {}, { preserveScroll: true });
}

function badgeToken(conta: ContaAnuncio): string {
    if (conta.token_valido === false) return '✗ token inválido';
    if (conta.escreve_habilitado) return '✓ ads_read + ads_management';
    if (conta.token_scopes.includes('ads_read')) return '✓ ads_read';
    return 'token não testado';
}
</script>

<template>
    <div class="flex max-w-[1180px] flex-col gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <select v-model.number="filtros.conta" :class="fieldClass" @change="aplicar()">
                <option :value="null">Todas as contas ativas</option>
                <option v-for="c in props.contas" :key="c.id" :value="c.id">{{ c.nome }}</option>
            </select>
            <select v-model.number="filtros.periodo" :class="fieldClass" @change="aplicar()">
                <option v-for="p in props.opcoes.periodos" :key="p" :value="p">{{ p }} dias</option>
            </select>
            <select
                v-model="filtros.nivel"
                :class="fieldClass"
                :disabled="filtros.campanha !== null || filtros.conjunto !== null"
                @change="aplicar()"
            >
                <option v-for="n in props.opcoes.niveis" :key="n.value" :value="n.value">
                    {{ n.label }}
                </option>
            </select>
            <button
                v-if="filtros.campanha !== null || filtros.conjunto !== null"
                type="button"
                class="cursor-pointer rounded-lg border border-border bg-card px-2.5 py-1.5 text-[12.5px] text-muted-foreground"
                @click="limparDrill"
            >
                ← voltar
            </button>
            <button
                type="button"
                class="ml-auto cursor-pointer rounded-[7px] border border-primary bg-primary px-3.5 py-2 text-[12.5px] text-primary-foreground"
                @click="sincronizarAgora"
            >
                Sincronizar agora
            </button>
        </div>

        <p
            v-if="props.contas.length === 0"
            class="m-0 rounded-[10px] border border-border bg-card px-[17px] py-4 text-[12.5px] text-muted-foreground"
        >
            Nenhuma conta de anúncio cadastrada. Adicione uma abaixo para trazer o investimento.
        </p>

        <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="k in props.kpis"
                :key="k.label"
                class="flex flex-col gap-1.5 rounded-[10px] border border-border bg-card px-[17px] py-[15px]"
            >
                <span class="font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-muted-foreground uppercase">
                    {{ k.label }}
                </span>
                <span class="font-[family-name:var(--font-crm-display)] text-[24px] leading-none font-medium">
                    {{ k.value }}
                </span>
                <span v-if="k.secundario" class="text-[11px] text-muted-foreground">{{ k.secundario }}</span>
            </div>
        </div>

        <div
            v-if="props.serie.length > 0"
            class="rounded-[10px] border border-border bg-card px-5 py-4"
        >
            <div class="mb-2 text-[10px] tracking-[0.1em] text-muted-foreground uppercase">
                Investido por dia · pontos = leads do CRM
            </div>
            <div class="flex h-24 items-end gap-0.5">
                <span
                    v-for="p in props.serie"
                    :key="p.referencia"
                    class="relative flex-1 rounded-t-sm bg-primary"
                    :style="{ height: `${(p.investido_centavos / serieMax) * 100}%` }"
                    :title="`${p.referencia}: ${brl(p.investido_centavos)} · ${p.leads_crm} lead(s)`"
                >
                    <span
                        v-if="p.leads_crm > 0"
                        class="absolute -top-1.5 left-1/2 size-1.5 -translate-x-1/2 rounded-full bg-primary"
                    />
                </span>
            </div>
        </div>

        <div class="overflow-x-auto rounded-[10px] border border-border bg-card">
            <table class="w-full border-collapse text-[12.5px]">
                <thead>
                    <tr class="bg-card text-left text-[10px] tracking-[0.1em] text-muted-foreground uppercase">
                        <th class="px-3 py-2.5">{{ filtros.nivel }}</th>
                        <th class="px-3 py-2.5 text-right">Investido</th>
                        <th class="px-3 py-2.5 text-right">CPM</th>
                        <th class="px-3 py-2.5 text-right">CTR</th>
                        <th class="px-3 py-2.5 text-right">Leads Meta</th>
                        <th class="px-3 py-2.5 text-right">Leads CRM</th>
                        <th class="px-3 py-2.5 text-right">CPL CRM</th>
                        <th class="px-3 py-2.5 text-right">Reuniões</th>
                        <th class="px-3 py-2.5 text-right">Contratos</th>
                        <th class="px-3 py-2.5 text-right">ROAS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="l in props.linhas"
                        :key="l.objeto_id"
                        class="border-t border-[#F2EFE8]"
                        :class="filtros.nivel !== 'anuncio' ? 'cursor-pointer hover:bg-card' : ''"
                        @click="filtros.nivel !== 'anuncio' && drill(l)"
                    >
                        <td class="px-3 py-2.5">
                            <span class="font-medium text-foreground">{{ l.nome }}</span>
                            <span
                                v-if="l.effective_status && l.effective_status !== 'ACTIVE'"
                                class="ml-1 rounded-full bg-muted px-1.5 py-0.5 text-[10px] text-muted-foreground"
                            >
                                {{ l.effective_status }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-right">{{ brl(l.investido_centavos) }}</td>
                        <td class="px-3 py-2.5 text-right text-muted-foreground">{{ brl(l.cpm_centavos) }}</td>
                        <td class="px-3 py-2.5 text-right text-muted-foreground">{{ l.ctr }}%</td>
                        <td class="px-3 py-2.5 text-right text-muted-foreground">{{ l.leads_meta }}</td>
                        <td class="px-3 py-2.5 text-right">
                            {{ l.leads_crm }}
                            <span
                                v-if="l.leads_meta > 0 && Math.abs(l.leads_meta - l.leads_crm) / l.leads_meta > 0.4"
                                :title="`Meta atribuiu ${l.leads_meta}, o CRM registrou ${l.leads_crm}`"
                                class="text-primary"
                                >⚠</span
                            >
                        </td>
                        <td class="px-3 py-2.5 text-right">{{ brl(l.cpl_crm_centavos) }}</td>
                        <td class="px-3 py-2.5 text-right text-muted-foreground">{{ l.reunioes }}</td>
                        <td class="px-3 py-2.5 text-right">{{ l.contratos }}</td>
                        <td class="px-3 py-2.5 text-right font-medium">
                            {{ l.roas.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}
                        </td>
                    </tr>
                    <tr v-if="props.linhas.length === 0">
                        <td colspan="10" class="px-3 py-6 text-center text-muted-foreground">
                            Sem dados no período. Rode "Sincronizar agora".
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Contas -->
        <div class="flex flex-col gap-3 rounded-[10px] border border-border bg-card px-5 py-4">
            <div class="flex items-center justify-between">
                <h2 class="m-0 font-[family-name:var(--font-crm-display)] text-[16px] font-medium">
                    Contas de anúncio
                </h2>
                <button
                    type="button"
                    class="cursor-pointer rounded-lg border border-dashed border-[#C7C1B4] px-3 py-1.5 text-[12px] text-muted-foreground hover:border-primary"
                    @click="abrirNova"
                >
                    + Nova conta
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
                        act_{{ c.ad_account_id }} · {{ c.moeda ?? '—' }} · {{ badgeToken(c) }}
                    </span>
                    <Deferred :data="`saude`">
                        <template #fallback>
                            <span class="ml-2 inline-block h-3 w-24 animate-pulse rounded bg-primary/15" />
                        </template>
                        <span
                            v-if="props.saude?.[c.id]"
                            class="ml-2 text-[11px]"
                            :class="props.saude[c.id].ok ? 'text-accent' : 'text-[#9B3B2F]'"
                        >
                            {{ props.saude[c.id].ok
                                ? `ao vivo: ${props.saude[c.id].nome ?? 'ok'}`
                                : (props.saude[c.id].mensagem ?? 'sem acesso') }}
                        </span>
                    </Deferred>
                </div>
                <div class="flex shrink-0 gap-2">
                    <a :href="c.gerenciador_url" target="_blank" rel="noopener" class="text-[11.5px] text-foreground underline">Gerenciador ↗</a>
                    <button type="button" class="cursor-pointer text-[11.5px] text-muted-foreground underline" @click="testar(c)">Testar</button>
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
                    ad_account_id (dígitos)
                    <input
                        v-model="form.ad_account_id"
                        type="text"
                        inputmode="numeric"
                        required
                        :disabled="!!contaAtual"
                        :class="fieldClass"
                    />
                    <span v-if="form.errors.ad_account_id" class="text-[#9B3B2F]">{{ form.errors.ad_account_id }}</span>
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-muted-foreground">
                    business_id (opcional)
                    <input v-model="form.business_id" type="text" :class="fieldClass" />
                </label>
                <label class="flex flex-col gap-1 text-[11px] text-muted-foreground">
                    Access token (ads_read)
                    <div class="flex gap-1.5">
                        <input
                            v-model="form.access_token"
                            :type="mostrarToken ? 'text' : 'password'"
                            autocomplete="off"
                            :required="!contaAtual"
                            :placeholder="contaAtual ? `${contaAtual.token_mascarado} — em branco mantém` : 'EAA…'"
                            class="min-w-0 flex-1 rounded-lg border border-border bg-card px-2.5 py-1.5 font-[family-name:var(--font-crm-mono)] text-[12px]"
                        />
                        <button type="button" class="cursor-pointer rounded-lg border border-border bg-card px-2 text-[11px]" @click="mostrarToken = !mostrarToken">
                            {{ mostrarToken ? 'Ocultar' : 'Ver' }}
                        </button>
                    </div>
                    <span v-if="form.errors.access_token" class="text-[#9B3B2F]">{{ form.errors.access_token }}</span>
                </label>
                <div class="flex gap-2 sm:col-span-2">
                    <button type="submit" :disabled="form.processing" class="cursor-pointer rounded-[7px] border border-primary bg-primary px-3.5 py-2 text-[12px] text-primary-foreground disabled:opacity-60">
                        Salvar
                    </button>
                    <button type="button" class="cursor-pointer rounded-[7px] border border-border bg-card px-3.5 py-2 text-[12px] text-muted-foreground" @click="modoNovaConta = false; contaEdicao = null">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
