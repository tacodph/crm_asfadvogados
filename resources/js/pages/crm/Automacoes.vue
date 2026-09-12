<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { TERMOS } from '@/data/crm';

const regrasAtivas = reactive<Record<number, boolean>>({
    1: true,
    2: true,
    3: false,
    4: true,
});

const defs = [
    {
        id: 1,
        nome: 'Resposta em até 15 minutos',
        quando: 'novo lead entra',
        se: 'canal = WhatsApp',
        entao: 'notificar plantonista + criar tarefa',
        execucoes: '312 execuções · 96% no SLA',
        ok: true,
    },
    {
        id: 2,
        nome: 'Follow-up após silêncio',
        quando: 'sem resposta há 3 dias',
        se: 'etapa ≠ Fechamento',
        entao: 'tarefa para o responsável',
        execucoes: '148 execuções · 41% reengajam',
        ok: true,
    },
    {
        id: 3,
        nome: 'Reengajamento de perdidos',
        quando: 'lead perdido há 90 dias',
        se: 'consentimento vigente',
        entao: 'mensagem informativa única',
        execucoes: 'pausada pelo comitê ético',
        ok: false,
    },
    {
        id: 4,
        nome: 'Validade de proposta',
        quando: '48h antes do vencimento',
        se: 'proposta não respondida',
        entao: 'alerta ao responsável',
        execucoes: '67 execuções · 29% aceite',
        ok: true,
    },
];

const regras = computed(() =>
    defs.map((r) => {
        const on = regrasAtivas[r.id];

        return {
            ...r,
            estado: on ? 'ativa' : 'pausada',
            estadoCor: on ? 'var(--accent)' : 'var(--muted-foreground)',
            trackBg: on ? 'var(--accent)' : '#D6D1C6',
            knobLeft: on ? 16 : 2,
            selo: r.ok
                ? { texto: '✓ parecer ético anexado', cor: 'var(--accent)' }
                : { texto: '⚠ requer revisão do comitê', cor: 'var(--primary)' },
        };
    }),
);

const msg = ref(
    'Olá! Confirmamos o recebimento do seu contato. Em breve um advogado retorna com as orientações iniciais.',
);

const flags = computed(() =>
    TERMOS.filter((t) => t.re.test(msg.value)).map((t) => ({
        nivel: t.nivel === 'bloqueio' ? 'bloqueio' : 'atenção',
        titulo: t.titulo,
        norma: t.norma,
        bg: t.nivel === 'bloqueio' ? 'color-mix(in srgb, var(--destructive) 18%, transparent)' : 'color-mix(in srgb, var(--primary) 12%, transparent)',
        borda: t.nivel === 'bloqueio' ? '#E8C9C3' : 'color-mix(in srgb, var(--primary) 35%, transparent)',
        cor: t.nivel === 'bloqueio' ? '#9B3B2F' : 'var(--primary)',
    })),
);

const bloqueado = computed(() =>
    flags.value.some((f) => f.nivel === 'bloqueio'),
);

function toggleRegra(id: number): void {
    regrasAtivas[id] = !regrasAtivas[id];
}
</script>

<template>
    <div
        class="grid max-w-[1180px] items-start gap-4 lg:grid-cols-[1.1fr_1fr]"
    >
        <div class="flex flex-col gap-3">
            <div
                v-for="r in regras"
                :key="r.id"
                class="flex flex-col gap-[11px] rounded-[10px] border border-border bg-card px-[17px] py-[15px]"
            >
                <div class="flex items-center justify-between gap-2.5">
                    <span class="text-[13.5px] font-medium">{{ r.nome }}</span>
                    <button
                        type="button"
                        class="flex cursor-pointer items-center gap-[7px] border-0 bg-transparent p-0"
                        @click="toggleRegra(r.id)"
                    >
                        <span class="text-[11px]" :style="{ color: r.estadoCor }">
                            {{ r.estado }}
                        </span>
                        <span
                            class="relative block h-[18px] w-8 rounded-full"
                            :style="{ background: r.trackBg }"
                        >
                            <span
                                class="absolute top-0.5 h-3.5 w-3.5 rounded-full bg-card"
                                :style="{ left: `${r.knobLeft}px` }"
                            />
                        </span>
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-[7px]">
                    <span
                        class="rounded-md bg-secondary px-[9px] py-1 text-[11.5px] text-muted-foreground"
                    >
                        quando · {{ r.quando }}
                    </span>
                    <span class="text-[#B8B1A3]">→</span>
                    <span
                        class="rounded-md bg-secondary px-[9px] py-1 text-[11.5px] text-muted-foreground"
                    >
                        se · {{ r.se }}
                    </span>
                    <span class="text-[#B8B1A3]">→</span>
                    <span
                        class="rounded-md bg-primary/15 px-[9px] py-1 text-[11.5px] text-primary"
                    >
                        então · {{ r.entao }}
                    </span>
                </div>
                <div
                    class="flex items-center justify-between border-t border-[#F2EFE8] pt-[9px]"
                >
                    <span class="text-[11.5px] text-muted-foreground">{{
                        r.execucoes
                    }}</span>
                    <span class="text-[11px]" :style="{ color: r.selo.cor }">{{
                        r.selo.texto
                    }}</span>
                </div>
            </div>
        </div>

        <div
            class="flex flex-col gap-[13px] rounded-[10px] border border-border bg-card px-5 pt-[18px] pb-5"
        >
            <div class="flex flex-col gap-1">
                <h2
                    class="m-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                >
                    Revisor ético de mensagens
                </h2>
                <p class="m-0 text-xs leading-normal text-muted-foreground">
                    Todo texto automatizado passa por validação antes de
                    publicar. Edite abaixo para ver o parecer.
                </p>
            </div>
            <textarea
                v-model="msg"
                rows="5"
                class="resize-y rounded-lg border border-border bg-card px-[13px] py-3 font-[family-name:var(--font-crm-body)] text-[13px] leading-relaxed text-foreground outline-none"
            />
            <div class="flex flex-col gap-[9px]">
                <div
                    v-for="(f, i) in flags"
                    :key="i"
                    class="flex items-start gap-2.5 rounded-lg border px-3 py-[11px]"
                    :style="{ background: f.bg, borderColor: f.borda }"
                >
                    <span
                        class="shrink-0 pt-0.5 font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.1em] uppercase"
                        :style="{ color: f.cor }"
                    >
                        {{ f.nivel }}
                    </span>
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[12.5px] text-foreground">{{
                            f.titulo
                        }}</span>
                        <span class="text-[11.5px] leading-snug text-muted-foreground">{{
                            f.norma
                        }}</span>
                    </div>
                </div>
                <p
                    v-if="flags.length === 0"
                    class="m-0 text-[12.5px] text-accent"
                >
                    Nenhum apontamento — texto apto à publicação.
                </p>
            </div>
            <button
                type="button"
                class="cursor-pointer rounded-lg border px-2.5 py-2.5 text-[13px] font-medium"
                :class="
                    bloqueado
                        ? 'border-border bg-muted text-[#9A9384]'
                        : 'border-primary bg-primary text-primary-foreground'
                "
            >
                {{
                    bloqueado
                        ? 'Publicação bloqueada — corrija os apontamentos'
                        : 'Publicar automação'
                }}
            </button>
        </div>
    </div>
</template>
