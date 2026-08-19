<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import AuthError from '@/components/auth/AuthError.vue';
import AuthPasswordInput from '@/components/auth/AuthPasswordInput.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
/* @chisel-registration */
import { register } from '@/routes';
/* @end-chisel-registration */
import { store } from '@/routes/login';
import { request } from '@/routes/password';
/* @chisel-passkeys */
/* @end-chisel-passkeys */

defineOptions({
    layout: {
        title: 'Entrar no CRM',
        description:
            'Use suas credenciais do escritório. O acesso é pessoal e registrado.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Entrar" />

    <div v-if="status" class="auth-status">
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-3.5"
    >
        <div class="flex flex-col gap-1.5">
            <label class="auth-label" for="email">E-mail profissional</label>
            <input
                id="email"
                type="email"
                name="email"
                required
                autofocus
                :tabindex="1"
                autocomplete="email"
                placeholder="nome@asfadvogados.adv.br"
                class="auth-input"
            />
        </div>

        <div class="flex flex-col gap-1.5">
            <span class="flex items-center justify-between gap-3">
                <label class="auth-label" for="password">Senha</label>
                <Link
                    v-if="canResetPassword"
                    :href="request()"
                    class="auth-link text-[11.5px]"
                    :tabindex="5"
                >
                    Esqueci minha senha
                </Link>
            </span>
            <AuthPasswordInput
                id="password"
                name="password"
                required
                :tabindex="2"
                autocomplete="current-password"
                placeholder="••••••••"
            />
        </div>

        <AuthError :message="errors.email || errors.password" />

        <label class="flex cursor-pointer items-center gap-2">
            <input
                id="remember"
                type="checkbox"
                name="remember"
                :tabindex="3"
                class="peer sr-only"
            />
            <span class="auth-check">✓</span>
            <span class="text-[12.5px] text-[#3C4450]">
                Manter sessão neste dispositivo
            </span>
        </label>

        <button
            type="submit"
            class="auth-btn-primary"
            :tabindex="4"
            :disabled="processing"
            data-test="login-button"
        >
            <span v-if="processing" class="auth-spinner" />
            <span>{{ processing ? 'Validando…' : 'Entrar' }}</span>
        </button>

    </Form>

    <!-- @chisel-passkeys -->
    <PasskeyVerify
        theme="auth"
        label="Entrar com passkey"
        loading-label="Autenticando..."
        separator=""
    />
    <!-- @end-chisel-passkeys -->

    <!-- @chisel-registration -->
    <p class="auth-footer-text">
        Sem credenciais?
        <Link :href="register()" class="auth-link" :tabindex="6">
            Solicitar acesso
        </Link>
    </p>
    <!-- @end-chisel-registration -->
</template>
