<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AuthError from '@/components/auth/AuthError.vue';
import AuthPasswordInput from '@/components/auth/AuthPasswordInput.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
/* @chisel-passkeys */
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
/* @end-chisel-passkeys */
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Confirme sua senha',
        description:
            'Área segura. Confirme sua senha para continuar. O acesso é registrado na auditoria.',
    },
});
</script>

<template>
    <Head title="Confirmar senha" />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
        class="flex flex-col gap-3.5"
    >
        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="password">Senha</label>
            <AuthPasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                autofocus
                placeholder="••••••••"
            />
        </div>
        <AuthError :message="errors.password" />

        <button
            type="submit"
            class="auth-btn-primary"
            :disabled="processing"
            data-test="confirm-password-button"
        >
            <span v-if="processing" class="auth-spinner" />
            <span>{{ processing ? 'Confirmando…' : 'Confirmar senha' }}</span>
        </button>
    </Form>

    <!-- @chisel-passkeys -->
    <PasskeyVerify
        theme="auth"
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="Confirmar com passkey"
        loading-label="Confirmando..."
        separator=""
    />
    <!-- @end-chisel-passkeys -->
</template>
