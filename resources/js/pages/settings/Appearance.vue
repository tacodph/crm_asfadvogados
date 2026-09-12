<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Aparência" />

    <div
        class="mx-auto flex max-w-[720px] flex-col gap-6 rounded-[10px] border border-border bg-card px-5 py-5"
    >
        <Heading
            variant="small"
            title="Aparência"
            description="Atualize o tema da interface"
        />
        <AppearanceTabs />
    </div>
</template>
