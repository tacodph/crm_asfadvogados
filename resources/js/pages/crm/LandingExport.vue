<script setup lang="ts">
defineProps<{
    disponivel: boolean;
    previewUrl: string;
}>();
</script>

<template>
    <div class="flex max-w-[1100px] flex-col gap-4">
        <div
            class="flex flex-col gap-3 rounded-[10px] border border-border bg-card px-5 py-4"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-col gap-1">
                    <h2
                        class="m-0 font-[family-name:var(--font-crm-display)] text-[17px] font-medium"
                    >
                        Landing pública em HTML estático
                    </h2>
                    <p class="m-0 text-[12.5px] leading-[1.6] text-muted-foreground">
                        Mesma página do endereço <code>/</code> (Welcome.vue),
                        compilada em arquivos sem Laravel — publique em qualquer
                        hospedagem estática, subdomínio ou no site do escritório.
                    </p>
                </div>
                <a
                    :href="disponivel ? '/landing-export/download' : undefined"
                    :aria-disabled="!disponivel"
                    class="rounded-lg border border-primary bg-primary px-4 py-2 text-[12.5px] font-medium text-primary-foreground"
                    :class="disponivel ? '' : 'pointer-events-none opacity-40'"
                >
                    Baixar .zip
                </a>
            </div>

            <p
                v-if="!disponivel"
                class="m-0 rounded-lg bg-primary/10 px-3 py-2 text-[12px] text-primary"
            >
                Ainda não compilada. Rode
                <code>npm run build:landing</code> (ou <code>npm run build</code>,
                que já inclui) e recarregue.
            </p>

            <ol
                class="m-0 flex flex-col gap-1 pl-4 text-[12px] leading-[1.7] text-muted-foreground"
            >
                <li>
                    Ajuste os destinos dos botões com
                    <code>VITE_LANDING_CRM_URL</code> antes do build (padrão:
                    domínio do CRM).
                </li>
                <li>
                    <code>npm run build:landing</code> gera
                    <code>public/landing/</code>.
                </li>
                <li>
                    Baixe o .zip aqui e suba o conteúdo na raiz da hospedagem —
                    os caminhos dos assets são relativos.
                </li>
            </ol>
        </div>

        <div
            v-if="disponivel"
            class="flex flex-col gap-2 rounded-[10px] border border-border bg-[#F5EFE4] p-2.5"
        >
            <span
                class="px-1 font-[family-name:var(--font-crm-mono)] text-[10px] tracking-[0.12em] text-muted-foreground uppercase"
            >
                Pré-visualização · public/landing/index.html
            </span>
            <iframe
                :src="previewUrl"
                title="Pré-visualização da landing"
                class="h-[70vh] w-full rounded-lg border border-border bg-card"
            />
        </div>
    </div>
</template>
