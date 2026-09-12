<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { update } from '@/actions/App/Http/Controllers/Settings/TenantUserController';
import Heading from '@/components/Heading.vue';
import { index as usersIndex } from '@/routes/settings/users';

type RoleOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
        role_label: string;
        especialidades: string[];
        ausente_ate: string | null;
    };
    roles: RoleOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Usuários',
                href: usersIndex(),
            },
            {
                title: 'Editar',
                href: '#',
            },
        ],
    },
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role: props.user.role,
    especialidades: props.user.especialidades.join(', '),
    ausente_ate: props.user.ausente_ate ?? '',
});

const fieldClass =
    'w-full rounded-lg border border-input bg-background px-3 py-2 text-sm outline-none focus-visible:border-ring';

const submit = (): void => {
    form.patch(update.url({ user: props.user.id }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`Editar ${user.name}`" />

    <div class="flex max-w-xl flex-col space-y-6">
        <div class="flex flex-col gap-1">
            <Link
                :href="usersIndex()"
                class="text-xs text-muted-foreground hover:text-foreground"
            >
                ← Usuários
            </Link>
            <Heading
                variant="small"
                title="Editar usuário"
                :description="user.email"
            />
        </div>

        <form class="flex flex-col gap-4" @submit.prevent="submit">
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
                    >Nova senha (opcional)</label
                >
                <input
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                    :class="fieldClass"
                />
                <p v-if="form.errors.password" class="text-xs text-destructive">
                    {{ form.errors.password }}
                </p>
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-medium text-muted-foreground"
                    >Confirmar nova senha</label
                >
                <input
                    v-model="form.password_confirmation"
                    type="password"
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
                    :class="fieldClass"
                />
            </div>
            <div class="flex flex-col gap-1.5">
                <label class="text-xs font-medium text-muted-foreground"
                    >Ausente até</label
                >
                <input
                    v-model="form.ausente_ate"
                    type="date"
                    :class="fieldClass"
                />
            </div>
            <div class="flex gap-2 pt-1">
                <button
                    type="submit"
                    class="inline-flex cursor-pointer items-center rounded-lg bg-primary px-3.5 py-2 text-sm text-primary-foreground disabled:opacity-60"
                    :disabled="form.processing"
                >
                    Salvar
                </button>
                <Link
                    :href="usersIndex()"
                    class="inline-flex items-center rounded-lg border border-border px-3.5 py-2 text-sm text-foreground"
                >
                    Cancelar
                </Link>
            </div>
        </form>
    </div>
</template>
