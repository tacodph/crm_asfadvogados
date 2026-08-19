<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { KeyRound } from '@lucide/vue';
import InputError from '@/components/InputError.vue';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
    theme?: 'default' | 'auth';
};

const props = defineProps<Props>();

const { verify, isLoading, error, isSupported } = usePasskeyVerify({
    ...(props.routes
        ? {
              routes: {
                  options: props.routes.options.url,
                  submit: props.routes.submit.url,
              },
          }
        : {}),
    onSuccess: (response) => {
        router.visit(response.redirect ?? '/dashboard');
    },
});
</script>

<template>
    <div v-if="isSupported">
        <div class="grid gap-2">
            <button
                type="button"
                :class="
                    theme === 'auth'
                        ? 'auth-btn-ghost'
                        : 'inline-flex w-full items-center justify-center gap-2 rounded-md border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-accent'
                "
                @click="verify"
                :disabled="isLoading"
            >
                <KeyRound class="h-4 w-4" />
                {{
                    isLoading
                        ? (props.loadingLabel ?? 'Authenticating...')
                        : (props.label ?? 'Sign in with a passkey')
                }}
            </button>

            <div v-if="error" class="text-center">
                <InputError :message="error" />
            </div>
        </div>

        <div
            v-if="theme === 'auth' && (props.separator ?? 'ou continue com e-mail')"
            class="auth-divider my-5"
        >
            {{ props.separator ?? 'ou continue com e-mail' }}
        </div>

        <div v-else class="relative my-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-border" />
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-background px-2 text-muted-foreground">
                    {{ props.separator ?? 'Or continue with email' }}
                </span>
            </div>
        </div>
    </div>
</template>
