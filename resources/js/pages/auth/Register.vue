<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AuthError from '@/components/auth/AuthError.vue';
import AuthPasswordInput from '@/components/auth/AuthPasswordInput.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Solicitar acesso',
        description:
            'Preencha seus dados. O cadastro é restrito a colaboradores do escritório.',
    },
});
</script>

<template>
    <Head title="Cadastro" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-3.5"
    >
        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="name">Nome</label>
            <input
                id="name"
                type="text"
                required
                autofocus
                :tabindex="1"
                autocomplete="name"
                name="name"
                placeholder="Seu nome completo"
                class="auth-input"
            />
        </div>
        <AuthError :message="errors.name" />

        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="email">E-mail profissional</label>
            <input
                id="email"
                type="email"
                required
                :tabindex="2"
                autocomplete="email"
                name="email"
                placeholder="nome@asfadvogados.adv.br"
                class="auth-input"
            />
        </div>
        <AuthError :message="errors.email" />

        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="password">Senha</label>
            <AuthPasswordInput
                id="password"
                required
                :tabindex="3"
                autocomplete="new-password"
                name="password"
                placeholder="••••••••"
                :passwordrules="passwordRules"
            />
        </div>
        <AuthError :message="errors.password" />

        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="password_confirmation">
                Confirmar senha
            </label>
            <AuthPasswordInput
                id="password_confirmation"
                required
                :tabindex="4"
                autocomplete="new-password"
                name="password_confirmation"
                placeholder="••••••••"
                :passwordrules="passwordRules"
            />
        </div>
        <AuthError :message="errors.password_confirmation" />

        <button
            type="submit"
            class="auth-btn-primary"
            tabindex="5"
            :disabled="processing"
            data-test="register-user-button"
        >
            <span v-if="processing" class="auth-spinner" />
            <span>{{ processing ? 'Criando conta…' : 'Criar conta' }}</span>
        </button>

        <p class="auth-footer-text">
            Já tem acesso?
            <Link :href="login()" class="auth-link" :tabindex="6">
                Entrar
            </Link>
        </p>
    </Form>
</template>
