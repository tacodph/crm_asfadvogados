<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthError from '@/components/auth/AuthError.vue';
import AuthPasswordInput from '@/components/auth/AuthPasswordInput.vue';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Redefinir senha',
        description: 'Defina uma nova senha para o seu acesso ao CRM.',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Redefinir senha" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-3.5"
    >
        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="email">E-mail profissional</label>
            <input
                id="email"
                type="email"
                name="email"
                autocomplete="email"
                v-model="inputEmail"
                readonly
                class="auth-input"
            />
        </div>
        <AuthError :message="errors.email" />

        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="password">Nova senha</label>
            <AuthPasswordInput
                id="password"
                name="password"
                autocomplete="new-password"
                autofocus
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
                name="password_confirmation"
                autocomplete="new-password"
                placeholder="••••••••"
                :passwordrules="passwordRules"
            />
        </div>
        <AuthError :message="errors.password_confirmation" />

        <button
            type="submit"
            class="auth-btn-primary"
            :disabled="processing"
            data-test="reset-password-button"
        >
            <span v-if="processing" class="auth-spinner" />
            <span>{{ processing ? 'Salvando…' : 'Redefinir senha' }}</span>
        </button>
    </Form>
</template>
