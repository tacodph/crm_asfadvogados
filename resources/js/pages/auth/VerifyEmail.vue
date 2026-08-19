<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Verifique seu e-mail',
        description:
            'Clique no link que enviamos para o seu e-mail profissional para ativar o acesso.',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Verificação de e-mail" />

    <div
        v-if="status === 'verification-link-sent'"
        class="auth-status"
    >
        Um novo link de verificação foi enviado para o e-mail informado no
        cadastro.
    </div>

    <Form
        v-bind="send.form()"
        class="flex flex-col gap-3.5"
        v-slot="{ processing }"
    >
        <button
            type="submit"
            class="auth-btn-primary"
            :disabled="processing"
        >
            <span v-if="processing" class="auth-spinner" />
            <span>
                {{
                    processing
                        ? 'Reenviando…'
                        : 'Reenviar e-mail de verificação'
                }}
            </span>
        </button>

        <Link
            :href="logout()"
            as="button"
            class="auth-link mx-auto block text-sm"
        >
            Sair
        </Link>
    </Form>
</template>
