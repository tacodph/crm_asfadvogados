<script setup lang="ts">
import { computed, ref } from 'vue';
import { mensagemRevisorPadrao, termos, travas } from '@/data/landing';

const msg = ref(mensagemRevisorPadrao);

const flags = computed(() => {
    const encontradas = termos
        .filter((termo) => termo.re.test(msg.value))
        .map((termo) => ({
            nivel: termo.nivel,
            titulo: termo.titulo,
            norma: termo.norma,
            bg: termo.nivel === 'bloqueio' ? '#FCF5F3' : '#FBF7EF',
            borda: termo.nivel === 'bloqueio' ? '#E6C9C2' : 'color-mix(in srgb, var(--primary) 35%, transparent)',
            cor: termo.nivel === 'bloqueio' ? '#9B3B2F' : 'var(--primary)',
        }));

    if (encontradas.length) {
        return encontradas;
    }

    return [
        {
            nivel: 'aprovado',
            titulo: 'Texto liberado para uso automatizado',
            norma: 'Sem promessa de resultado, comparação ou apelo mercantilista. Parecer anexado ao modelo.',
            bg: '#F1F6F4',
            borda: 'color-mix(in srgb, var(--accent) 35%, transparent)',
            cor: 'var(--accent)',
        },
    ];
});
</script>

<template>
    <section
        id="compliance"
        class="mx-auto grid max-w-[1180px] items-start gap-10 px-7 pt-[26px] pb-[68px] lg:grid-cols-2 lg:gap-[44px]"
    >
        <div class="flex flex-col gap-5">
            <span
                class="font-[family-name:var(--font-landing-mono)] text-[9.5px] tracking-[0.14em] text-primary uppercase"
            >
                O diferencial que a OAB cobra
            </span>
            <h2
                class="m-0 font-[family-name:var(--font-landing-display)] text-[32px] leading-[1.12] font-normal tracking-[-0.02em] sm:text-[40px]"
            >
                Um CRM comum coloca o escritório em risco.
            </h2>
            <p class="m-0 text-[15.5px] leading-[1.7] text-muted-foreground">
                Ferramentas de vendas nasceram para disparo em massa, gatilho de
                urgência e comparação de preço — tudo o que a advocacia não pode
                fazer. O Antessala inverte a lógica: o que é vedado simplesmente
                não existe no produto.
            </p>
            <div class="flex flex-col gap-2.5 pt-1">
                <div
                    v-for="trava in travas"
                    :key="trava.titulo"
                    class="grid grid-cols-[20px_1fr] items-start gap-3 border-b border-border pb-[11px]"
                >
                    <span
                        class="grid h-5 w-5 place-items-center rounded-full bg-accent/15 text-[11px] text-accent"
                    >
                        ✓
                    </span>
                    <span class="flex flex-col gap-[3px]">
                        <span class="text-[13.5px] font-medium text-foreground">
                            {{ trava.titulo }}
                        </span>
                        <span
                            class="text-[12.5px] leading-[1.55] text-muted-foreground"
                        >
                            {{ trava.texto }}
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div
            class="flex flex-col gap-4 rounded-xl border border-border bg-card px-6 pt-6 pb-[26px]"
        >
            <div class="flex flex-col gap-[5px]">
                <h3
                    class="m-0 font-[family-name:var(--font-landing-display)] text-xl font-medium"
                >
                    Revisor ético de mensagens
                </h3>
                <p class="m-0 text-[13px] leading-[1.6] text-muted-foreground">
                    Todo texto automatizado passa por análise antes de ir ao ar.
                    Experimente: escreva algo que um vendedor escreveria.
                </p>
            </div>
            <textarea
                v-model="msg"
                rows="4"
                class="resize-y rounded-[9px] border border-border bg-card px-[13px] py-3 text-[13.5px] leading-[1.6] text-foreground outline-none focus:border-primary"
            />
            <div class="flex flex-col gap-[9px]">
                <div
                    v-for="flag in flags"
                    :key="flag.titulo"
                    class="flex items-start gap-[11px] rounded-[9px] border px-[13px] py-3"
                    :style="{
                        background: flag.bg,
                        borderColor: flag.borda,
                    }"
                >
                    <span
                        class="shrink-0 pt-0.5 font-[family-name:var(--font-landing-mono)] text-[9.5px] tracking-[0.1em] uppercase"
                        :style="{ color: flag.cor }"
                    >
                        {{ flag.nivel }}
                    </span>
                    <span class="flex flex-col gap-[3px]">
                        <span class="text-[13px] text-foreground">
                            {{ flag.titulo }}
                        </span>
                        <span class="text-xs leading-normal text-muted-foreground">
                            {{ flag.norma }}
                        </span>
                    </span>
                </div>
            </div>
            <span class="text-[11.5px] leading-[1.55] text-muted-foreground">
                O parecer fica anexado ao modelo e versionado — serve como trava
                preventiva e como prova de diligência.
            </span>
        </div>
    </section>
</template>
