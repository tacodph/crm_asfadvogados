<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AuthBrandPanel from '@/components/auth/AuthBrandPanel.vue';
import AuthLogoMark from '@/components/auth/AuthLogoMark.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="auth-page flex min-h-dvh w-full flex-col lg:flex-row">
        <AuthBrandPanel />

        <main class="grid flex-1 place-items-center px-8 py-10">
            <div class="auth-form-panel flex w-full max-w-[392px] flex-col gap-[22px]">
                <Link :href="home()" class="auth-brand-link lg:hidden">
                    <AuthLogoMark compact />
                </Link>

                <div v-if="title || description" class="flex flex-col gap-1.5">
                    <h2
                        v-if="title"
                        class="m-0 font-[family-name:var(--font-auth-display)] text-[28px] font-medium tracking-[-0.015em] text-[#171B21]"
                    >
                        {{ title }}
                    </h2>
                    <p
                        v-if="description"
                        class="m-0 text-[13.5px] leading-[1.55] text-[#77808E]"
                    >
                        {{ description }}
                    </p>
                </div>

                <slot />

                <div
                    class="flex flex-col gap-3 border-t border-[#E7E3DA] pt-[18px]"
                >
                    <p class="m-0 text-[11.5px] leading-[1.6] text-[#9AA2AE]">
                        Acesso restrito a colaboradores do escritório. O uso é
                        monitorado e registrado; o conteúdo dos casos é
                        protegido por sigilo profissional e visível apenas ao
                        advogado responsável.
                    </p>
                    <p class="m-0 text-[11.5px] text-[#9AA2AE]">
                        Problemas de acesso?
                        <span class="auth-link">Fale com o administrador</span>
                    </p>
                </div>
            </div>
        </main>
    </div>
</template>

<style>
.auth-page {
    --font-auth-body: 'IBM Plex Sans', system-ui, sans-serif;
    --font-auth-display: 'Newsreader', Georgia, serif;
    --font-auth-mono: 'IBM Plex Mono', ui-monospace, monospace;
    background: #f7f5f0;
    font-family: var(--font-auth-body);
    color: #171b21;
    -webkit-font-smoothing: antialiased;
}

.auth-form-panel {
    animation: auth-fade-up 220ms ease-out;
}

@keyframes auth-fade-up {
    from {
        transform: translateY(8px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.auth-page a {
    color: #8c6f3f;
    text-decoration: none;
}

.auth-page a:hover {
    color: #6f5730;
    text-decoration: underline;
}

.auth-page .auth-brand-link,
.auth-page .auth-brand-link:hover {
    color: inherit;
    text-decoration: none;
}
</style>
