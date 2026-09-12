<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AuthError from '@/components/auth/AuthError.vue';
import AuthPasswordInput from '@/components/auth/AuthPasswordInput.vue';
import { home } from '@/routes';
import { store } from '@/routes/tenants';

const props = defineProps<{
    plan: string;
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Criar sua conta',
        description:
            'Configure o escritório e comece a usar o Antessala agora — sem cartão de crédito.',
        restrictionNotice:
            'Você está criando o espaço do seu escritório no Antessala. Colegas podem ser convidados depois, dentro do próprio sistema.',
        showAdminHelp: false,
    },
});

const planos = [
    { value: 'essencial', label: 'Essencial' },
    { value: 'escritorio', label: 'Escritório' },
    { value: 'banca', label: 'Banca' },
];
</script>

<template>
    <Head title="Criar conta" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['owner_password', 'owner_password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-3.5"
    >
        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="name">Nome do escritório</label>
            <input
                id="name"
                type="text"
                required
                autofocus
                :tabindex="1"
                name="name"
                placeholder="ASF Advogados Associados"
                class="auth-input"
            />
        </div>
        <AuthError :message="errors.name" />

        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="plan">Plano</label>
            <select
                id="plan"
                name="plan"
                :tabindex="2"
                class="auth-input"
                :value="props.plan"
            >
                <option v-for="p in planos" :key="p.value" :value="p.value">
                    {{ p.label }}
                </option>
            </select>
        </div>
        <AuthError :message="errors.plan" />

        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="owner_name">Seu nome</label>
            <input
                id="owner_name"
                type="text"
                required
                :tabindex="3"
                autocomplete="name"
                name="owner_name"
                placeholder="Seu nome completo"
                class="auth-input"
            />
        </div>
        <AuthError :message="errors.owner_name" />

        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="owner_email">Seu e-mail</label>
            <input
                id="owner_email"
                type="email"
                required
                :tabindex="4"
                autocomplete="email"
                name="owner_email"
                placeholder="socio@escritorio.adv.br"
                class="auth-input"
            />
        </div>
        <AuthError :message="errors.owner_email" />

        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="owner_password">Senha</label>
            <AuthPasswordInput
                id="owner_password"
                required
                :tabindex="5"
                autocomplete="new-password"
                name="owner_password"
                placeholder="••••••••"
                :passwordrules="passwordRules"
            />
        </div>
        <AuthError :message="errors.owner_password" />

        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="owner_password_confirmation">
                Confirmar senha
            </label>
            <AuthPasswordInput
                id="owner_password_confirmation"
                required
                :tabindex="6"
                autocomplete="new-password"
                name="owner_password_confirmation"
                placeholder="••••••••"
                :passwordrules="passwordRules"
            />
        </div>
        <AuthError :message="errors.owner_password_confirmation" />

        <button
            type="submit"
            class="auth-btn-primary"
            tabindex="7"
            :disabled="processing"
            data-test="create-tenant-button"
        >
            <span v-if="processing" class="auth-spinner" />
            <span>{{ processing ? 'Criando escritório…' : 'Criar conta' }}</span>
        </button>

        <p class="auth-footer-text">
            Já tem uma conta?
            <Link :href="home()" class="auth-link" :tabindex="8">
                Voltar para a página inicial
            </Link>
        </p>
    </Form>
</template>
