<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AuthError from '@/components/auth/AuthError.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Recuperar acesso',
        description:
            'Informe o e-mail profissional cadastrado. Enviaremos um link para redefinir a senha.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Recuperar senha" />

    <div v-if="status" class="auth-status">
        {{ status }}
    </div>

    <Form
        v-bind="email.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-3.5"
    >
        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="email">E-mail profissional</label>
            <input
                id="email"
                type="email"
                name="email"
                autocomplete="off"
                autofocus
                placeholder="nome@asfadvogados.adv.br"
                class="auth-input"
            />
        </div>
        <AuthError :message="errors.email" />

        <button
            type="submit"
            class="auth-btn-primary"
            :disabled="processing"
            data-test="email-password-reset-link-button"
        >
            <span v-if="processing" class="auth-spinner" />
            <span>
                {{
                    processing
                        ? 'Enviando…'
                        : 'Enviar link de redefinição'
                }}
            </span>
        </button>
    </Form>

    <p class="auth-footer-text">
        Ou volte para
        <Link :href="login()" class="auth-link">entrar</Link>
    </p>
</template>
