<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import AuthError from '@/components/auth/AuthError.vue';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Código de recuperação',
            description:
                'Informe um dos seus códigos de recuperação de emergência para acessar sua conta.',
            buttonText: 'usar código do autenticador',
        };
    }

    return {
        title: 'Verificação em duas etapas',
        description:
            'Digite o código gerado pelo seu autenticador. O acesso será registrado na auditoria.',
        buttonText: 'usar código de recuperação',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head title="Autenticação em duas etapas" />

    <div class="flex flex-col gap-4">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                class="flex flex-col gap-4"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <InputOTP
                    id="otp"
                    v-model="code"
                    class="w-full gap-[9px]"
                    :maxlength="6"
                    :disabled="processing"
                    autofocus
                >
                    <InputOTPGroup class="w-full gap-[9px]">
                        <InputOTPSlot
                            v-for="index in 6"
                            :key="index"
                            :index="index - 1"
                            class="auth-otp-slot"
                        />
                    </InputOTPGroup>
                </InputOTP>
                <AuthError :message="errors.code" />
                <button
                    type="submit"
                    class="auth-btn-primary"
                    :disabled="processing"
                >
                    <span v-if="processing" class="auth-spinner" />
                    <span>
                        {{ processing ? 'Validando…' : 'Validar código' }}
                    </span>
                </button>
                <p class="auth-footer-text">
                    ou você pode
                    <button
                        type="button"
                        class="auth-link"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </p>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                class="flex flex-col gap-3.5"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <div class="flex flex-col gap-1.5">
                    <label class="auth-label" for="recovery_code">
                        Código de recuperação
                    </label>
                    <input
                        id="recovery_code"
                        name="recovery_code"
                        type="text"
                        placeholder="Digite o código de recuperação"
                        :autofocus="showRecoveryInput"
                        required
                        class="auth-input"
                    />
                </div>
                <AuthError :message="errors.recovery_code" />
                <button
                    type="submit"
                    class="auth-btn-primary"
                    :disabled="processing"
                >
                    <span v-if="processing" class="auth-spinner" />
                    <span>
                        {{ processing ? 'Validando…' : 'Validar código' }}
                    </span>
                </button>
                <div class="flex items-center justify-between gap-2.5">
                    <button
                        type="button"
                        class="auth-btn-ghost w-auto px-3.5 py-2 text-[12.5px]"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        Voltar
                    </button>
                    <span class="text-[12px] text-[#77808E]">
                        ou
                        <button
                            type="button"
                            class="auth-link"
                            @click="() => toggleRecoveryMode(clearErrors)"
                        >
                            {{ authConfigContent.buttonText }}
                        </button>
                    </span>
                </div>
            </Form>
        </template>
    </div>
</template>
