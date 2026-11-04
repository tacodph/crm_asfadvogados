<script setup lang="ts">
import { router, usePage, usePoll } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { NotificacaoLembrete } from '@/types/crm';
import NotificacaoController from '@/actions/App/Http/Controllers/NotificacaoController';

const page = usePage();
const notificacoes = computed(() => page.props.notificacoes);
const naoLidas = computed(() => notificacoes.value?.naoLidas ?? 0);
const itens = computed(() => notificacoes.value?.itens ?? []);

// Os lembretes são gerados pelo agendador a cada 5 minutos; recarrega só
// as props compartilhadas leves para o sino e o contador do menu.
usePoll(60_000, { only: ['notificacoes', 'crmCounts'] });

watch(naoLidas, (atual, anterior) => {
    if (anterior === undefined || atual <= anterior) {
        return;
    }

    const novo = itens.value[0];

    if (novo) {
        toast.info(`${novo.tipoLabel}: ${novo.descricao}`, {
            description: [novo.hora, novo.lead].filter(Boolean).join(' · '),
        });
    }
});

function abrir(item: NotificacaoLembrete): void {
    router.patch(NotificacaoController.abrir.url({ notificacao: item.id }));
}

function marcarTodas(): void {
    router.post(
        NotificacaoController.marcarTodasComoLidas.url(),
        {},
        { preserveScroll: true, preserveState: true },
    );
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="topbar-icon-btn relative"
                title="Notificações"
            >
                <Bell class="size-[18px]" />
                <span
                    v-if="naoLidas > 0"
                    class="absolute -right-0.5 -top-0.5 grid min-w-4 place-items-center rounded-full bg-[#9B3B2F] px-1 text-[10px] font-semibold leading-4 text-white"
                >
                    {{ naoLidas > 9 ? '9+' : naoLidas }}
                </span>
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            class="border-border bg-popover w-80 rounded-lg"
            align="end"
            :side-offset="8"
        >
            <DropdownMenuLabel
                class="flex items-center justify-between px-2 py-2 font-normal"
            >
                <span class="text-foreground text-[13px] font-medium">
                    Lembretes
                </span>
                <button
                    v-if="naoLidas > 0"
                    type="button"
                    class="text-primary cursor-pointer border-0 bg-transparent p-0 text-[11.5px]"
                    @click="marcarTodas"
                >
                    Marcar todos como lidos
                </button>
            </DropdownMenuLabel>
            <DropdownMenuSeparator class="bg-border" />
            <p
                v-if="itens.length === 0"
                class="text-muted-foreground m-0 px-2 py-4 text-center text-[12.5px]"
            >
                Nenhum lembrete pendente.
            </p>
            <DropdownMenuItem
                v-for="item in itens"
                :key="item.id"
                class="flex cursor-pointer flex-col items-start gap-0.5 px-2 py-2"
                @select="abrir(item)"
            >
                <span class="text-foreground text-[12.5px] font-medium">
                    {{ item.tipoLabel }}: {{ item.descricao }}
                </span>
                <span class="text-muted-foreground text-[11.5px]">
                    {{ item.hora ?? 'hoje' }}
                    <template v-if="item.lead"> · {{ item.lead }}</template>
                    · {{ item.assunto }}
                </span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
