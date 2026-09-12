<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ChevronUp } from '@lucide/vue';
import LandingAbout from '@/components/landing/LandingAbout.vue';
import LandingCta from '@/components/landing/LandingCta.vue';
import LandingFaq from '@/components/landing/LandingFaq.vue';
import LandingFeatures from '@/components/landing/LandingFeatures.vue';
import LandingFooter from '@/components/landing/LandingFooter.vue';
import LandingHeader from '@/components/landing/LandingHeader.vue';
import LandingHero from '@/components/landing/LandingHero.vue';
import LandingPlans from '@/components/landing/LandingPlans.vue';
import { useLandingOnePage } from '@/composables/useLandingOnePage';

const {
    isSticky,
    mobileMenuOpen,
    activeSection,
    showBackToTop,
    toggleMobileMenu,
    closeMobileMenu,
    scrollToTop,
} = useLandingOnePage();
</script>

<template>
    <Head title="LexStart — CRM jurídico pré-processual" />

    <div class="landing-page w-full overflow-x-hidden">
        <LandingHeader
            :is-sticky="isSticky"
            :mobile-menu-open="mobileMenuOpen"
            :active-section="activeSection"
            @toggle-mobile-menu="toggleMobileMenu"
            @close-mobile-menu="closeMobileMenu"
        />
        <LandingHero />
        <LandingFeatures />
        <LandingAbout />
        <LandingPlans />
        <LandingFaq />
        <LandingCta />
        <LandingFooter />

        <button
            id="back-to-top"
            type="button"
            class="fixed bottom-10 right-10 flex size-10 items-center justify-center rounded-md bg-purple-500 text-white transition-opacity"
            :style="{ opacity: showBackToTop ? 1 : 0 }"
            @click="scrollToTop"
        >
            <ChevronUp class="size-5" />
        </button>
    </div>
</template>
