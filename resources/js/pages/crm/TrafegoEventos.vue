<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import CrmDrawer from '@/components/crm/CrmDrawer.vue';
import { index, show } from '@/actions/App/Http/Controllers/TrafegoEventoController';
import { reenviar } from '@/routes/trafego/eventos';
import type {
    EventoDetalhe,
    EventoFiltros,
    EventoOpcoes,
    EventosPaginados,
} from '@/types/trafego';

const props = defineProps<{
    eventos: EventosPaginados;
    filtros: EventoFiltros;
    contadores: Record<string, number>;
    opcoes: EventoOpcoes;
    detalhe: EventoDetalhe | null;
}>();

const filtros = reactive({ ...props.filtros });
const drawerAberto = ref(false);

const fieldClass =
    'rounded-lg border border-border bg-card px-2.5 py-1.5 text-[12.5px] text-foreground outline-none focus:border-primary';

function aplicar(): void {
    router.get(index.url(), { ...filtros }, {
        only: ['eventos', 'contadores', 'filtros'],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function abrir(id: number): void {
    drawerAberto.value = true;
    router.get(
        show.url({ evento: id }),
        {},
        { only: ['detalhe'], preserveState: true, preserveScroll: true },
    );
}

function reenviarEvento(id: number): void {
    router.post(reenviar.url({ evento: id }), {}, { preserveScroll: true });
}

function fmt(iso: string | null): string {
    return iso ? new Date(iso).toLocaleString('pt-BR') : '—';
}
</script>

<template>
    <div class="flex max-w-[1180px] flex-col gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <select v-model="filtros.campanha" :class="fieldClass" @change="aplicar">
                <option value="">Todas as campanhas</option>
                <option v-for="c in props.opcoes.campanhas" :key="c.slug" :value="c.slug">
                    {{ c.nome }}
                </option>
            </select>
            <select v-model="filtros.status" :class="fieldClass" @change="aplicar">
                <option value="">Todos os status</option>
                <option v-for="s in props.opcoes.status" :key="s.value" :value="s.value">
                    {{ s.label }} ({{ props.contadores[s.value] ?? 0 }})
                </option>
            </select>
            <select v-model="filtros.event_name" :class="fieldClass" @change="aplicar">
                <option value="">Todos os eventos</option>
                <option v-for="e in props.opcoes.eventos" :key="e.value" :value="e.value">
                    {{ e.label }}
                </option>
            </select>
            <select v-model.number="filtros.periodo" :class="fieldClass" @change="aplicar">
                <option v-for="p in props.opcoes.periodos" :key="p" :value="p">
                    {{ p }} dias
                </option>
            </select>
            <input
                v-model="filtros.busca"
                type="search"
                placeholder="event_id / fbtrace_id"
                :class="fieldClass"
                class="min-w-[200px]"
                @keyup.enter="aplicar"
            />
        </div>

        <div class="overflow-x-auto rounded-[10px] border border-border bg-card">
            <table class="w-full border-collapse text-[12.5px]">
                <thead>
                    <tr class="bg-card text-left text-[10px] tracking-[0.1em] text-muted-foreground uppercase">
                        <th class="px-3 py-2.5">Evento</th>
                        <th class="px-3 py-2.5">Campanha</th>
                        <th class="px-3 py-2.5">Status</th>
                        <th class="px-3 py-2.5">Quando</th>
                        <th class="px-3 py-2.5">Resultado</th>
                        <th class="px-3 py-2.5"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="ev in props.eventos.data"
                        :key="ev.id"
                        class="border-t border-[#F2EFE8] hover:bg-card"
                    >
                        <td class="px-3 py-2.5">
                            <div class="font-medium text-foreground">
                                {{ ev.event_name_label }}
                                <span v-if="ev.is_teste" class="text-[10px] text-primary">· teste</span>
                            </div>
                            <div class="font-[family-name:var(--font-crm-mono)] text-[10.5px] text-muted-foreground">
                                {{ ev.event_id }}
                            </div>
                        </td>
                        <td class="px-3 py-2.5 text-muted-foreground">{{ ev.campanha }}</td>
                        <td class="px-3 py-2.5">
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px]"
                                :style="{ color: ev.status_cor, background: `${ev.status_cor}1A` }"
                            >
                                {{ ev.status_label }}
                            </span>
                            <span v-if="ev.motivo_descarte" class="ml-1 text-[10.5px] text-muted-foreground">
                                {{ ev.motivo_descarte }}
                            </span>
                        </td>
                        <td class="px-3 py-2.5 text-muted-foreground">{{ fmt(ev.created_at) }}</td>
                        <td class="px-3 py-2.5 text-muted-foreground">
                            <template v-if="ev.status === 'enviado'">
                                {{ ev.events_received ?? 0 }} recebido(s)
                            </template>
                            <template v-else-if="ev.error_message">
                                {{ ev.error_code ? `[${ev.error_code}] ` : '' }}{{ ev.error_message }}
                            </template>
                            <template v-else>—</template>
                        </td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            <button
                                type="button"
                                class="cursor-pointer text-foreground underline"
                                @click="abrir(ev.id)"
                            >
                                Detalhe
                            </button>
                            <button
                                v-if="ev.status === 'erro'"
                                type="button"
                                class="ml-2 cursor-pointer text-[#9B3B2F] underline"
                                @click="reenviarEvento(ev.id)"
                            >
                                Reenviar
                            </button>
                        </td>
                    </tr>
                    <tr v-if="props.eventos.data.length === 0">
                        <td colspan="6" class="px-3 py-6 text-center text-muted-foreground">
                            Nenhum evento no período/filtro.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between text-[12px] text-muted-foreground">
            <span>
                {{ props.eventos.total }} evento(s) · página {{ props.eventos.current_page }} de
                {{ props.eventos.last_page }}
            </span>
            <span class="flex gap-2">
                <Link
                    v-if="props.eventos.prev_page_url"
                    :href="props.eventos.prev_page_url"
                    preserve-scroll
                    class="rounded-md border border-border bg-card px-2.5 py-1 text-foreground"
                >
                    Anterior
                </Link>
                <Link
                    v-if="props.eventos.next_page_url"
                    :href="props.eventos.next_page_url"
                    preserve-scroll
                    class="rounded-md border border-border bg-card px-2.5 py-1 text-foreground"
                >
                    Próxima
                </Link>
            </span>
        </div>
    </div>

    <div
        v-if="drawerAberto && props.detalhe"
        class="fixed inset-0 z-40 flex justify-end bg-black/20"
        @click.self="drawerAberto = false"
    >
        <CrmDrawer
            :title="props.detalhe.event_name_label"
            :subtitle="props.detalhe.event_id"
            subtitle-mono
            @close="drawerAberto = false"
        >
            <div class="flex flex-col gap-4 text-[12.5px]">
                <div class="grid grid-cols-2 gap-2 text-muted-foreground">
                    <div><span class="text-muted-foreground">Status:</span> {{ props.detalhe.status_label }}</div>
                    <div><span class="text-muted-foreground">Tentativas:</span> {{ props.detalhe.tentativas }}</div>
                    <div><span class="text-muted-foreground">HTTP:</span> {{ props.detalhe.http_status ?? '—' }}</div>
                    <div><span class="text-muted-foreground">fbtrace:</span> {{ props.detalhe.fbtrace_id ?? '—' }}</div>
                    <div><span class="text-muted-foreground">Contato:</span> {{ props.detalhe.contato ?? '—' }}</div>
                    <div><span class="text-muted-foreground">Negociação:</span> {{ props.detalhe.negociacao_id ?? '—' }}</div>
                </div>
                <div>
                    <div class="mb-1 text-[10px] tracking-[0.1em] text-muted-foreground uppercase">request_payload</div>
                    <pre class="overflow-x-auto rounded-lg border border-border bg-card p-3 font-[family-name:var(--font-crm-mono)] text-[11px]">{{ JSON.stringify(props.detalhe.request_payload, null, 2) }}</pre>
                </div>
                <div v-if="props.detalhe.response_body">
                    <div class="mb-1 text-[10px] tracking-[0.1em] text-muted-foreground uppercase">response_body</div>
                    <pre class="overflow-x-auto rounded-lg border border-border bg-card p-3 font-[family-name:var(--font-crm-mono)] text-[11px]">{{ JSON.stringify(props.detalhe.response_body, null, 2) }}</pre>
                </div>
            </div>
        </CrmDrawer>
    </div>
</template>
