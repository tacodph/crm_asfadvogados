<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { edit as negociacaoEdit } from '@/routes/negociacoes';
import { index as propostasIndex } from '@/routes/propostas';

type Clausula = { titulo: string; texto: string };

type PropostaDetalhe = {
    id: number;
    codigo: string;
    titulo: string;
    cliente: string;
    versao: string;
    versaoNumero: number;
    status: string;
    statusBg: string;
    statusCor: string;
    prazo: string;
    prazoCor: string;
    valor: string;
    escopo: string;
    parcelamento: string | null;
    indiceReajuste: string | null;
    modeloOrigem: string | null;
    autor: string | null;
    enviadoEm: string | null;
    aceitoEm: string | null;
    clausulas: Clausula[];
    negociacao: {
        id: number;
        assunto: string;
        funil: string;
        etapa: string;
        responsavel: string;
        valorFmt: string;
    };
    contatoDetalhe: {
        id: number;
        nome: string;
        email: string | null;
        telefone: string | null;
        cargo: string | null;
    };
    empresaDetalhe: {
        id: number;
        nome: string;
        cnpj: string | null;
        porte: string | null;
    } | null;
};

const props = defineProps<{
    proposta: PropostaDetalhe;
}>();

function fmt(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString('pt-BR') : '—';
}
</script>

<template>
    <div class="mx-auto flex max-w-[920px] flex-col gap-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <Link
                    :href="propostasIndex()"
                    class="text-[12px] text-muted-foreground hover:text-foreground"
                >
                    ← Propostas
                </Link>
                <h1
                    class="mt-2 mb-1 font-[family-name:var(--font-crm-display)] text-[26px] leading-tight font-medium"
                >
                    {{ props.proposta.codigo }}
                    <span class="text-muted-foreground">·</span>
                    {{ props.proposta.titulo }}
                </h1>
                <p class="m-0 text-[13px] text-muted-foreground">
                    {{ props.proposta.cliente }} · {{ props.proposta.versao }}
                </p>
            </div>
            <span
                class="rounded-full px-3 py-1 text-[12px]"
                :style="{
                    background: props.proposta.statusBg,
                    color: props.proposta.statusCor,
                }"
            >
                {{ props.proposta.status }}
            </span>
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div
                class="rounded-[10px] border border-border bg-card px-4 py-3"
            >
                <div
                    class="font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                >
                    Honorários
                </div>
                <div
                    class="mt-1 font-[family-name:var(--font-crm-display)] text-[22px] font-medium"
                >
                    {{ props.proposta.valor }}
                </div>
            </div>
            <div
                class="rounded-[10px] border border-border bg-card px-4 py-3"
            >
                <div
                    class="font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                >
                    Validade
                </div>
                <div
                    class="mt-1 text-[15px] font-medium"
                    :style="{ color: props.proposta.prazoCor }"
                >
                    {{ props.proposta.prazo }}
                </div>
            </div>
            <div
                class="rounded-[10px] border border-border bg-card px-4 py-3"
            >
                <div
                    class="font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                >
                    Modelo
                </div>
                <div class="mt-1 text-[13px] text-muted-foreground">
                    {{ props.proposta.modeloOrigem ?? '—' }}
                </div>
            </div>
        </div>

        <div
            class="grid gap-4 rounded-[10px] border border-border bg-card px-5 py-4 lg:grid-cols-2"
        >
            <div>
                <h2
                    class="mt-0 mb-3 font-[family-name:var(--font-crm-display)] text-[16px] font-medium"
                >
                    Negociação
                </h2>
                <dl class="m-0 grid gap-2 text-[12.5px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Assunto</dt>
                        <dd class="m-0 text-right text-foreground">
                            {{ props.proposta.negociacao.assunto }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Funil</dt>
                        <dd class="m-0 text-right">
                            {{ props.proposta.negociacao.funil }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Etapa</dt>
                        <dd class="m-0 text-right">
                            {{ props.proposta.negociacao.etapa }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Responsável</dt>
                        <dd class="m-0 text-right">
                            {{ props.proposta.negociacao.responsavel }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Valor estimado</dt>
                        <dd class="m-0 text-right font-[family-name:var(--font-crm-mono)]">
                            {{ props.proposta.negociacao.valorFmt }}
                        </dd>
                    </div>
                </dl>
                <Link
                    :href="negociacaoEdit(props.proposta.negociacao.id)"
                    class="mt-3 inline-block text-[12px] text-primary underline-offset-2 hover:underline"
                >
                    Abrir negociação →
                </Link>
            </div>

            <div>
                <h2
                    class="mt-0 mb-3 font-[family-name:var(--font-crm-display)] text-[16px] font-medium"
                >
                    Contato e empresa
                </h2>
                <dl class="m-0 grid gap-2 text-[12.5px]">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Contato</dt>
                        <dd class="m-0 text-right">
                            {{ props.proposta.contatoDetalhe.nome }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Cargo</dt>
                        <dd class="m-0 text-right">
                            {{ props.proposta.contatoDetalhe.cargo ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">E-mail</dt>
                        <dd class="m-0 text-right">
                            {{ props.proposta.contatoDetalhe.email ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Telefone</dt>
                        <dd class="m-0 text-right">
                            {{ props.proposta.contatoDetalhe.telefone ?? '—' }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Empresa</dt>
                        <dd class="m-0 text-right">
                            {{ props.proposta.empresaDetalhe?.nome ?? '— (PF)' }}
                        </dd>
                    </div>
                    <div
                        v-if="props.proposta.empresaDetalhe"
                        class="flex justify-between gap-3"
                    >
                        <dt class="text-muted-foreground">CNPJ</dt>
                        <dd class="m-0 text-right font-[family-name:var(--font-crm-mono)]">
                            {{ props.proposta.empresaDetalhe.cnpj ?? '—' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <div
            class="rounded-[10px] border border-border bg-card px-5 py-4"
        >
            <h2
                class="mt-0 mb-2 font-[family-name:var(--font-crm-display)] text-[16px] font-medium"
            >
                Escopo
            </h2>
            <p class="m-0 text-[13px] leading-relaxed text-muted-foreground">
                {{ props.proposta.escopo }}
            </p>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <div>
                    <div class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase">
                        Parcelamento
                    </div>
                    <div class="mt-1 text-[12.5px]">
                        {{ props.proposta.parcelamento ?? '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase">
                        Reajuste
                    </div>
                    <div class="mt-1 text-[12.5px]">
                        {{ props.proposta.indiceReajuste ?? '—' }}
                    </div>
                </div>
                <div>
                    <div class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase">
                        Autor
                    </div>
                    <div class="mt-1 text-[12.5px]">
                        {{ props.proposta.autor ?? '—' }}
                    </div>
                </div>
            </div>
        </div>

        <div
            class="rounded-[10px] border border-border bg-card px-5 py-4"
        >
            <h2
                class="mt-0 mb-3 font-[family-name:var(--font-crm-display)] text-[16px] font-medium"
            >
                Cláusulas da minuta
            </h2>
            <div class="flex flex-col gap-3">
                <div
                    v-for="(clausula, i) in props.proposta.clausulas"
                    :key="i"
                    class="rounded-lg border border-[#F2EFE8] bg-card px-3.5 py-3"
                >
                    <div class="text-[12px] font-medium text-foreground">
                        {{ clausula.titulo }}
                    </div>
                    <p class="mt-1 mb-0 text-[12.5px] leading-relaxed text-muted-foreground">
                        {{ clausula.texto }}
                    </p>
                </div>
            </div>
        </div>

        <div
            class="grid gap-2 rounded-[10px] border border-[#F2EFE8] bg-card px-4 py-3 text-[11.5px] text-muted-foreground sm:grid-cols-2"
        >
            <div>Enviada em: {{ fmt(props.proposta.enviadoEm) }}</div>
            <div>Aceita em: {{ fmt(props.proposta.aceitoEm) }}</div>
        </div>
    </div>
</template>
