<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import LandingCompliance from '@/components/landing/LandingCompliance.vue';
import LandingCta from '@/components/landing/LandingCta.vue';
import LandingFaq from '@/components/landing/LandingFaq.vue';
import LandingFeatures from '@/components/landing/LandingFeatures.vue';
import LandingFooter from '@/components/landing/LandingFooter.vue';
import LandingHeader from '@/components/landing/LandingHeader.vue';
import LandingHero from '@/components/landing/LandingHero.vue';
import LandingPlans from '@/components/landing/LandingPlans.vue';
import LandingScreens from '@/components/landing/LandingScreens.vue';
import LandingSegments from '@/components/landing/LandingSegments.vue';
</script>

<template>
    <Head title="Antessala — CRM jurídico pré-processual">
        <link rel="preconnect" href="https://fonts.googleapis.com" />
        <link
            rel="preconnect"
            href="https://fonts.gstatic.com"
            crossorigin=""
        />
        <link
            href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&display=swap"
            rel="stylesheet"
        />
    </Head>

    <div
        class="landing-page w-full overflow-x-hidden bg-[#F7F5F0] font-[family-name:var(--font-landing-body)] text-[#171B21] antialiased"
    >
        <LandingHeader />
        <LandingHero />
        <LandingSegments />
        <LandingFeatures />
        <LandingScreens />
        <LandingCompliance />
        <LandingPlans />
        <LandingFaq />
        <LandingCta />
        <LandingFooter />
    </div>
</template>

<style>
.landing-page {
    --font-landing-body: 'IBM Plex Sans', system-ui, sans-serif;
    --font-landing-display: 'Newsreader', Georgia, serif;
    --font-landing-mono: 'IBM Plex Mono', ui-monospace, monospace;
    scroll-behavior: smooth;
}

.landing-page a {
    text-decoration: none;
}
</style>
