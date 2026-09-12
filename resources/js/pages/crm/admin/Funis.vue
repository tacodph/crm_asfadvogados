<script setup lang="ts">
import { Link, router, useForm } from '@inertiajs/vue3';
import {
    destroy,
    edit,
    store,
} from '@/actions/App/Http/Controllers/Admin/FunilController';
import { index as adminIndex } from '@/routes/admin';
import { index as negociacoesIndex } from '@/routes/negociacoes';

type Funil = {
    id: number;
    slug: string;
    nome: string;
    distribuicao: string;
    ordem: number;
    etapas_count: number;
};

defineProps<{
    funis: Funil[];
}>();

const form = useForm({
    nome: '',
    slug: '',
    distribuicao: 'round robin simples',
    ordem: 0,
});

const fieldClass =
    'w-full rounded-lg border border-border bg-card px-3 py-2 text-[13px] text-foreground outline-none focus:border-primary';

const submit = (): void => {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('nome', 'slug');
            form.distribuicao = 'round robin simples';
            form.ordem = 0;
        },
    });
};

const remove = (id: number): void => {
    if (!confirm('Excluir este funil e suas etapas?')) {
        return;
    }

    router.delete(destroy.url({ funil: id }), { preserveScroll: true });
};
</script>

<template>
    <div class="mx-auto flex max-w-[980px] flex-col gap-4">
        <div class="flex flex-col gap-1">
            <div class="flex flex-wrap items-center gap-3">
                <Link
                    :href="negociacoesIndex()"
                    class="text-[12px] text-muted-foreground hover:text-foreground"
                >
                    ← Funil
                </Link>
                <Link
                    :href="adminIndex()"
                    class="text-[12px] text-muted-foreground hover:text-foreground"
                >
                    Administração
                </Link>
            </div>
            <h1
                class="m-0 font-[family-name:var(--font-crm-display)] text-[22px] font-medium text-foreground"
            >
                Funis e etapas
            </h1>
        </div>

        <form
            class="grid grid-cols-1 gap-3 rounded-[10px] border border-border bg-card px-4 py-4 md:grid-cols-[1fr_1fr_1.2fr_100px_auto]"
            @submit.prevent="submit"
        >
            <input
                v-model="form.nome"
                type="text"
                required
                placeholder="Nome"
                :class="fieldClass"
            />
            <input
                v-model="form.slug"
                type="text"
                placeholder="Slug (opcional)"
                :class="fieldClass"
            />
            <select
                v-model="form.distribuicao"
                required
                :class="fieldClass"
            >
                <option value="round robin simples">round robin simples</option>
                <option value="round robin por especialidade">
                    round robin por especialidade
                </option>
                <option value="por carga de trabalho">
                    por carga de trabalho
                </option>
            </select>
            <input
                v-model.number="form.ordem"
                type="number"
                min="0"
                :class="fieldClass"
            />
            <button
                type="submit"
                class="cursor-pointer rounded-[7px] border border-primary bg-primary px-3 py-2 text-[12.5px] text-primary-foreground disabled:opacity-60"
                :disabled="form.processing"
            >
                Adicionar
            </button>
            <p
                v-if="form.errors.nome || form.errors.slug"
                class="m-0 text-[12px] text-[#9B3B2F] md:col-span-5"
            >
                {{ form.errors.nome || form.errors.slug }}
            </p>
        </form>

        <div
            class="overflow-hidden rounded-[10px] border border-border bg-card"
        >
            <table class="w-full border-collapse">
                <thead>
                    <tr class="bg-card">
                        <th
                            class="px-4 py-2.5 text-left text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Funil
                        </th>
                        <th
                            class="px-4 py-2.5 text-left text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Distribuição
                        </th>
                        <th
                            class="px-4 py-2.5 text-right text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                        >
                            Etapas
                        </th>
                        <th class="px-4 py-2.5" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in funis"
                        :key="item.id"
                        class="border-t border-[#F2EFE8]"
                    >
                        <td class="px-4 py-3 text-[13px] text-foreground">
                            {{ item.nome }}
                            <div
                                class="font-[family-name:var(--font-crm-mono)] text-[11px] text-muted-foreground"
                            >
                                {{ item.slug }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-[12.5px] text-muted-foreground">
                            {{ item.distribuicao }}
                        </td>
                        <td
                            class="px-4 py-3 text-right font-[family-name:var(--font-crm-mono)] text-[12px]"
                        >
                            {{ item.etapas_count }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-2">
                                <Link
                                    :href="edit.url({ funil: item.id })"
                                    class="text-[12px] text-primary hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    type="button"
                                    class="cursor-pointer text-[12px] text-[#9B3B2F]"
                                    @click="remove(item.id)"
                                >
                                    Excluir
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
