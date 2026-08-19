<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AuthSplitLayout from '@/layouts/auth/AuthSplitLayout.vue';

const { title = '', description = '' } = defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <Head>
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin=""
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap"
            rel="stylesheet"
        />
    </Head>

    <AuthSplitLayout :title="title" :description="description">
        <slot />
    </AuthSplitLayout>
</template>
