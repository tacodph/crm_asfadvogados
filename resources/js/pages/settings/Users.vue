<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    destroy,
    edit,
    store,
} from '@/actions/App/Http/Controllers/Settings/TenantUserController';
import Heading from '@/components/Heading.vue';
import { index as usersIndex } from '@/routes/settings/users';

type RoleOption = {
    value: string;
    label: string;
};

type TenantUser = {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    especialidades: string[];
    ausente_ate: string | null;
    created_at: string | null;
};

defineProps<{
    users: TenantUser[];
    roles: RoleOption[];
    tenant: {
        name: string | null;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Usuários',
                href: usersIndex(),
            },
        ],
    },
});

const page = usePage();
const currentUserId = computed(() => page.props.auth.user?.id);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'member',
    especialidades: '',
    ausente_ate: '',
});

const fieldClass =
    'w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring';

const submit = (): void => {
    form.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset(
                'name',
                'email',
                'password',
                'password_confirmation',
                'especialidades',
                'ausente_ate',
            );
            form.role = 'member';
        },
    });
};

const remove = (id: number): void => {
    if (!confirm('Remover este usuário do escritório?')) {
        return;
    }

    router.delete(destroy.url({ user: id }), { preserveScroll: true });
};
</script>

<template>
    <Head title="Usuários do escritório" />

    <div class="flex max-w-4xl flex-col space-y-6">
        <Heading
            variant="small"
            title="Usuários"
            :description="
                tenant.name
                    ? `Gerencie quem acessa ${tenant.name}.`
                    : 'Gerencie os usuários do escritório.'
            "
        />

        <form
            class="grid grid-cols-1 gap-3 rounded-xl border border-border bg-card p-4 md:grid-cols-2"
            @submit.prevent="submit"
        >
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-medium text-muted-foreground"
                    >Nome</label
                >
                <input
                    v-model="form.name"
                    type="text"
                    required
                    :class="fieldClass"
                />
                <p v-if="form.errors.name" class="text-xs text-destructive">
                    {{ form.errors.name }}
                </p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-medium text-muted-foreground"
                    >E-mail</label
                >
                <input
                    v-model="form.email"
                    type="email"
                    required
                    :class="fieldClass"
                />
                <p v-if="form.errors.email" class="text-xs text-destructive">
                    {{ form.errors.email }}
                </p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-medium text-muted-foreground"
                    >Senha</label
                >
                <input
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="new-password"
                    :class="fieldClass"
                />
                <p v-if="form.errors.password" class="text-xs text-destructive">
                    {{ form.errors.password }}
                </p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-medium text-muted-foreground"
                    >Confirmar senha</label
                >
                <input
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    :class="fieldClass"
                />
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-medium text-muted-foreground"
                    >Perfil</label
                >
                <select v-model="form.role" required :class="fieldClass">
                    <option
                        v-for="role in roles"
                        :key="role.value"
                        :value="role.value"
                    >
                        {{ role.label }}
                    </option>
                </select>
                <p v-if="form.errors.role" class="text-xs text-destructive">
                    {{ form.errors.role }}
                </p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-medium text-muted-foreground"
                    >Especialidades (slugs, vírgula)</label
                >
                <input
                    v-model="form.especialidades"
                    type="text"
                    placeholder="ex.: alimentos, software-b2b"
                    :class="fieldClass"
                />
            </div>
            <div class="flex flex-col gap-1.5 md:col-span-2">
                <label class="text-xs font-medium text-muted-foreground"
                    >Ausente até</label
                >
                <input
                    v-model="form.ausente_ate"
                    type="date"
                    class="w-full max-w-xs rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring"
                />
            </div>
            <div class="md:col-span-2">
                <button
                    type="submit"
                    class="inline-flex cursor-pointer items-center rounded-lg bg-primary px-3.5 py-2 text-sm text-primary-foreground disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Adicionar usuário
                </button>
            </div>
        </form>

        <div class="overflow-hidden rounded-xl border border-border bg-card">
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-b border-border bg-muted/40 text-left">
                        <th class="px-4 py-2.5 font-medium text-muted-foreground">
                            Nome
                        </th>
                        <th class="px-4 py-2.5 font-medium text-muted-foreground">
                            Perfil
                        </th>
                        <th class="px-4 py-2.5 font-medium text-muted-foreground">
                            Status
                        </th>
                        <th class="px-4 py-2.5" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="item in users"
                        :key="item.id"
                        class="border-t border-border"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium text-foreground">
                                {{ item.name }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ item.email }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ item.role_label }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <span v-if="item.ausente_ate">
                                Ausente até {{ item.ausente_ate }}
                            </span>
                            <span v-else>Ativo</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3">
                                <Link
                                    :href="edit.url({ user: item.id })"
                                    class="text-xs text-foreground underline-offset-2 hover:underline"
                                >
                                    Editar
                                </Link>
                                <button
                                    v-if="item.id !== currentUserId"
                                    type="button"
                                    class="cursor-pointer text-xs text-destructive"
                                    @click="remove(item.id)"
                                >
                                    Remover
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
