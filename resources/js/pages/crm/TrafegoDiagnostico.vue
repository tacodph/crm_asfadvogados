<script setup lang="ts">
import { Deferred, router } from '@inertiajs/vue3';
import TrafegoTabs from '@/components/crm/TrafegoTabs.vue';
import { sincronizar } from '@/routes/trafego/diagnostico';
import type { DiagnosticoCampanha, DiagnosticoMeta } from '@/types/trafego';

const props = defineProps<{
    campanhas: DiagnosticoCampanha[];
    meta?: DiagnosticoMeta;
}>();

const emqLabels: Record<string, string> = {
    em: 'E-mail (em)',
    ph: 'Telefone (ph)',
    fbp_fbc: 'fbp / fbc',
    ip_ua: 'IP + user agent',
};

function sincronizarAgora(): void {
    router.post(sincronizar.url(), {}, { preserveScroll: true });
}

function fmt(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString('pt-BR') : '—';
}

function serieMax(c: DiagnosticoCampanha): number {
    return Math.max(
        1,
        ...c.serie_30d.flatMap((p) => [p.servidor ?? 0, p.navegador ?? 0]),
    );
}
</script>

<template>
    <div class="flex max-w-[1180px] flex-col gap-4">
        <TrafegoTabs />

        <div class="flex items-center justify-between">
            <p class="m-0 max-w-[680px] text-[12px] text-muted-foreground">
                Os números de "servidor" e "navegador" vêm da Meta e podem atrasar 24–72h. Os totais
                locais e o proxy de qualidade (EMQ) são calculados a partir do log de eventos do CRM.
            </p>
            <button
                type="button"
                class="shrink-0 cursor-pointer rounded-[7px] border border-primary bg-primary px-3.5 py-2 text-[12.5px] text-primary-foreground"
                @click="sincronizarAgora"
            >
                Sincronizar agora
            </button>
        </div>

        <p
            v-if="props.campanhas.length === 0"
            class="m-0 rounded-[10px] border border-border bg-card px-[17px] py-4 text-[12.5px] text-muted-foreground"
        >
            Nenhuma campanha ativa.
        </p>

        <div
            v-for="c in props.campanhas"
            :key="c.slug"
            class="flex flex-col gap-4 rounded-[10px] border border-border bg-card px-5 py-4"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="m-0 font-[family-name:var(--font-crm-display)] text-[16px] font-medium">
                        {{ c.nome }}
                    </h2>
                    <p class="mt-0.5 mb-0 font-[family-name:var(--font-crm-mono)] text-[11px] text-muted-foreground">
                        pixel {{ c.pixel_id }} · sync {{ fmt(c.ultima_sincronizacao) }}
                    </p>
                </div>
                <a
                    :href="c.link_events_manager"
                    target="_blank"
                    rel="noopener"
                    class="text-[12px] text-foreground underline"
                >
                    ver no Events Manager ↗
                </a>
            </div>

            <div class="grid gap-3 sm:grid-cols-4">
                <div
                    v-for="(valor, rotulo) in {
                        Enviados: c.totais.enviados,
                        'Com erro': c.totais.com_erro,
                        Descartados: c.totais.descartados,
                        'Dedup. (est.)': c.totais.deduplicados_estimados,
                    }"
                    :key="rotulo"
                    class="rounded-lg border border-[#F2EFE8] bg-card px-3 py-2"
                >
                    <div class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase">{{ rotulo }}</div>
                    <div class="font-[family-name:var(--font-crm-display)] text-[19px] font-medium">
                        {{ valor }}
                    </div>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1.5">
                    <div class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase">
                        Proxy EMQ · {{ c.emq_proxy.base }} evento(s) enviados
                    </div>
                    <div
                        v-for="campo in ['em', 'ph', 'fbp_fbc', 'ip_ua'] as const"
                        :key="campo"
                        class="flex items-center gap-2 text-[11.5px]"
                    >
                        <span class="w-[120px] shrink-0 text-muted-foreground">{{ emqLabels[campo] }}</span>
                        <span class="h-2 flex-1 overflow-hidden rounded-full bg-primary/15">
                            <span
                                class="block h-full rounded-full bg-accent"
                                :style="{ width: `${c.emq_proxy[campo]}%` }"
                            />
                        </span>
                        <span class="w-9 text-right text-muted-foreground">{{ c.emq_proxy[campo] }}%</span>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <div class="text-[10px] tracking-[0.1em] text-muted-foreground uppercase">
                        Servidor × navegador (30d, dados da Meta)
                    </div>
                    <div
                        v-if="c.serie_30d.length > 0"
                        class="flex h-16 items-end gap-0.5"
                    >
                        <span
                            v-for="(pt, i) in c.serie_30d"
                            :key="i"
                            class="flex-1 rounded-t-sm bg-primary"
                            :style="{ height: `${((pt.servidor ?? 0) / serieMax(c)) * 100}%` }"
                            :title="`${pt.referencia}: ${pt.servidor ?? 0} servidor / ${pt.navegador ?? 0} navegador`"
                        />
                    </div>
                    <p v-else class="m-0 text-[11.5px] text-muted-foreground">
                        Sem snapshots ainda — clique em "Sincronizar agora".
                    </p>
                </div>
            </div>

            <div v-if="Object.keys(c.erros_por_codigo).length > 0" class="flex flex-wrap gap-2">
                <span
                    v-for="(total, codigo) in c.erros_por_codigo"
                    :key="codigo"
                    class="rounded-full bg-destructive/15 px-2.5 py-0.5 text-[11px] text-[#9B3B2F]"
                >
                    {{ codigo }}: {{ total }}
                </span>
            </div>

            <Deferred :data="`meta`">
                <template #fallback>
                    <div class="h-4 w-40 animate-pulse rounded bg-primary/15" />
                </template>
                <p class="m-0 text-[11.5px] text-muted-foreground">
                    <template v-if="props.meta?.[c.slug]?.leitura_pixel_ok">
                        Pixel ao vivo:
                        {{ props.meta[c.slug].name ?? c.pixel_id }} ·
                        último disparo
                        {{
                            props.meta[c.slug].last_fired_time
                                ? fmt(props.meta[c.slug].last_fired_time)
                                : 'sem registro'
                        }}
                    </template>
                    <template v-else-if="props.meta?.[c.slug]?.pixel_ok">
                        Token válido para envio na CAPI ({{ c.pixel_id }}).
                        Metadados do pixel não estão disponíveis com tokens do
                        Events Manager.
                        <template v-if="c.ultimo_evento_em">
                            Último envio do CRM: {{ fmt(c.ultimo_evento_em) }}.
                        </template>
                    </template>
                    <template v-else>
                        Não foi possível validar a conexão com a Graph API.
                        Confira o token em /trafego e clique em Testar conexão.
                    </template>
                </p>
            </Deferred>
        </div>
    </div>
</template>
