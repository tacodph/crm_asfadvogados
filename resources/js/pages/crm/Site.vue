<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { SITE_INIT } from '@/data/crm';

const site = reactive({ ...SITE_INIT });
const publicado = reactive({ ...SITE_INIT });
const sujo = ref(false);

function markDirty(): void {
    sujo.value = true;
}

function publicar(): void {
    if (!sujo.value) {
        return;
    }

    Object.assign(publicado, site);
    sujo.value = false;
}

function descartar(): void {
    if (!sujo.value) {
        return;
    }

    Object.assign(site, publicado);
    sujo.value = false;
}

const siteParagrafos = computed(() =>
    site.descricao
        .split(/\n\s*\n/)
        .map((p) => p.trim())
        .filter(Boolean),
);

const siteTitulo1 = computed(() => site.titulo.split('\n')[0] || '');
const siteTitulo2 = computed(() => site.titulo.split('\n')[1] || '');
</script>

<template>
    <div
        class="grid max-w-[1320px] items-start gap-[18px] lg:grid-cols-[420px_minmax(0,1fr)]"
    >
        <div class="flex flex-col gap-3.5">
            <div
                class="flex items-center justify-between gap-3 rounded-[10px] border border-border bg-card px-[18px] py-4"
            >
                <span
                    class="rounded-full px-2.5 py-1 text-[11.5px]"
                    :class="
                        sujo
                            ? 'bg-primary/10 text-primary'
                            : 'bg-accent/15 text-accent'
                    "
                >
                    {{
                        sujo
                            ? 'alterações não publicadas'
                            : 'publicado · sincronizado com o site'
                    }}
                </span>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="cursor-pointer rounded-lg border border-border bg-card px-3 py-2 text-[12.5px] text-muted-foreground"
                        @click="descartar"
                    >
                        Descartar
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer rounded-lg border border-primary bg-primary px-3.5 py-2 text-[12.5px] font-medium text-primary-foreground"
                        @click="publicar"
                    >
                        Publicar
                    </button>
                </div>
            </div>

            <div
                class="flex flex-col gap-3.5 rounded-[10px] border border-border bg-card px-5 pt-[18px] pb-5"
            >
                <h2
                    class="m-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                >
                    Conteúdo da seção
                </h2>
                <label class="flex flex-col gap-1">
                    <span
                        class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-muted-foreground uppercase"
                    >
                        Olho da seção
                    </span>
                    <input
                        v-model="site.olho"
                        class="rounded-lg border border-border bg-card px-[11px] py-[9px] text-[13px] text-foreground outline-none"
                        @input="markDirty"
                    />
                </label>
                <label class="flex flex-col gap-1">
                    <span
                        class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-muted-foreground uppercase"
                    >
                        Título · uma linha por quebra
                    </span>
                    <textarea
                        v-model="site.titulo"
                        rows="2"
                        class="resize-y rounded-lg border border-border bg-card px-[11px] py-2.5 font-[family-name:var(--font-crm-display)] text-[17px] leading-snug text-foreground outline-none"
                        @input="markDirty"
                    />
                </label>
                <label class="flex flex-col gap-1">
                    <span
                        class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-muted-foreground uppercase"
                    >
                        Descrição · linha vazia separa parágrafos
                    </span>
                    <textarea
                        v-model="site.descricao"
                        rows="12"
                        class="resize-y rounded-lg border border-border bg-card px-3 py-[11px] text-[12.5px] leading-relaxed text-foreground outline-none"
                        @input="markDirty"
                    />
                </label>
            </div>

            <div
                class="flex flex-col gap-3.5 rounded-[10px] border border-border bg-card px-5 pt-[18px] pb-5"
            >
                <h2
                    class="m-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                >
                    Indicadores
                </h2>
                <div class="grid grid-cols-[92px_1fr] items-end gap-2.5">
                    <label class="flex flex-col gap-1">
                        <span
                            class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-muted-foreground uppercase"
                            >Número</span
                        >
                        <input
                            v-model="site.n1"
                            class="rounded-lg border border-border bg-card px-[11px] py-[9px] font-[family-name:var(--font-crm-mono)] text-[13px] outline-none"
                            @input="markDirty"
                        />
                    </label>
                    <label class="flex flex-col gap-1">
                        <span
                            class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-muted-foreground uppercase"
                            >Rótulo</span
                        >
                        <input
                            v-model="site.l1"
                            class="rounded-lg border border-border bg-card px-[11px] py-[9px] text-[13px] outline-none"
                            @input="markDirty"
                        />
                    </label>
                    <label class="flex flex-col gap-1">
                        <span
                            class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-muted-foreground uppercase"
                            >Número</span
                        >
                        <input
                            v-model="site.n2"
                            class="rounded-lg border border-border bg-card px-[11px] py-[9px] font-[family-name:var(--font-crm-mono)] text-[13px] outline-none"
                            @input="markDirty"
                        />
                    </label>
                    <label class="flex flex-col gap-1">
                        <span
                            class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-muted-foreground uppercase"
                            >Rótulo</span
                        >
                        <input
                            v-model="site.l2"
                            class="rounded-lg border border-border bg-card px-[11px] py-[9px] text-[13px] outline-none"
                            @input="markDirty"
                        />
                    </label>
                    <label class="flex flex-col gap-1">
                        <span
                            class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-muted-foreground uppercase"
                            >Número</span
                        >
                        <input
                            v-model="site.n3"
                            class="rounded-lg border border-border bg-card px-[11px] py-[9px] font-[family-name:var(--font-crm-mono)] text-[13px] outline-none"
                            @input="markDirty"
                        />
                    </label>
                    <label class="flex flex-col gap-1">
                        <span
                            class="font-[family-name:var(--font-crm-mono)] text-[9.5px] tracking-[0.11em] text-muted-foreground uppercase"
                            >Rótulo</span
                        >
                        <input
                            v-model="site.l3"
                            class="rounded-lg border border-border bg-card px-[11px] py-[9px] text-[13px] outline-none"
                            @input="markDirty"
                        />
                    </label>
                </div>
            </div>

            <Link
                href="/landing-export"
                class="flex items-center justify-between gap-3 rounded-[10px] border border-border bg-card px-[18px] py-[13px] text-[12.5px] text-muted-foreground hover:border-border"
            >
                <span>Exportar landing pública em HTML estático</span>
                <span class="text-primary">→</span>
            </Link>
        </div>

        <div class="flex min-w-0 flex-col gap-2.5 lg:sticky lg:top-0">
            <div class="flex items-center justify-between">
                <span
                    class="font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-muted-foreground uppercase"
                >
                    Pré-visualização · asfadvogados.adv.br
                </span>
                <span class="text-[11.5px] text-muted-foreground"
                    >atualiza conforme você digita</span
                >
            </div>
            <div
                class="overflow-hidden rounded-[10px] border border-border bg-[#F5EFE4]"
            >
                <div
                    class="flex items-center gap-[7px] border-b border-border bg-[#EFE8DB] px-[13px] py-2.5"
                >
                    <span class="h-[9px] w-[9px] rounded-full bg-[#D6CDBB]" />
                    <span class="h-[9px] w-[9px] rounded-full bg-[#D6CDBB]" />
                    <span class="h-[9px] w-[9px] rounded-full bg-[#D6CDBB]" />
                </div>
                <div
                    class="grid gap-8 px-6 py-8 sm:grid-cols-[0.82fr_1.18fr] sm:gap-10 sm:px-10 sm:py-12"
                >
                    <div class="flex flex-col gap-3.5">
                        <span
                            class="text-[11.5px] font-medium tracking-[0.18em] text-primary uppercase"
                        >
                            {{ site.olho }}
                        </span>
                        <h2
                            class="m-0 font-[family-name:var(--font-crm-display)] text-[28px] leading-[1.14] font-normal tracking-[-0.015em] text-[#1A1712] sm:text-[36px]"
                        >
                            <span class="block">{{ siteTitulo1 }}</span>
                            <span class="block">{{ siteTitulo2 }}</span>
                        </h2>
                    </div>
                    <div class="flex flex-col gap-[22px]">
                        <div class="flex flex-col gap-5">
                            <p
                                v-for="(texto, i) in siteParagrafos"
                                :key="i"
                                class="m-0 text-[14px] leading-[1.72] text-[#3B352C] sm:text-[15px]"
                            >
                                {{ texto }}
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap gap-8 border-t border-[#DDD2BE] pt-6 sm:gap-[52px]"
                        >
                            <div class="flex flex-col gap-2">
                                <span
                                    class="font-[family-name:var(--font-crm-display)] text-[28px] leading-none font-medium text-[#1A1712] sm:text-[34px]"
                                >
                                    {{ site.n1 }}
                                </span>
                                <span class="text-[13px] text-[#6E6558]">{{
                                    site.l1
                                }}</span>
                            </div>
                            <div class="flex flex-col gap-2">
                                <span
                                    class="font-[family-name:var(--font-crm-display)] text-[28px] leading-none font-medium text-[#1A1712] sm:text-[34px]"
                                >
                                    {{ site.n2 }}
                                </span>
                                <span class="text-[13px] text-[#6E6558]">{{
                                    site.l2
                                }}</span>
                            </div>
                            <div class="flex flex-col gap-2">
                                <span
                                    class="font-[family-name:var(--font-crm-display)] text-[28px] leading-none font-medium text-[#1A1712] sm:text-[34px]"
                                >
                                    {{ site.n3 }}
                                </span>
                                <span class="text-[13px] text-[#6E6558]">{{
                                    site.l3
                                }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
