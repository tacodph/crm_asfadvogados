import { onMounted, onUnmounted, ref } from 'vue';

const sectionIds = ['home', 'features', 'about', 'pricing', 'contact'] as const;

export function useLandingOnePage() {
    const isSticky = ref(false);
    const mobileMenuOpen = ref(false);
    const activeSection = ref<string>('home');
    const showBackToTop = ref(false);

    function handleScroll(): void {
        const scrollTop =
            window.scrollY || document.documentElement.scrollTop;

        isSticky.value = scrollTop >= 50;
        showBackToTop.value = scrollTop >= 50;

        let current = sectionIds[0];

        for (const id of sectionIds) {
            const element = document.getElementById(id);

            if (!element) {
                continue;
            }

            const offset = element.getBoundingClientRect().top + scrollTop - 100;

            if (scrollTop >= offset) {
                current = id;
            }
        }

        activeSection.value = current;
    }

    function toggleMobileMenu(): void {
        mobileMenuOpen.value = !mobileMenuOpen.value;
    }

    function closeMobileMenu(): void {
        mobileMenuOpen.value = false;
    }

    function scrollToTop(): void {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function animateCounters(): void {
        document.querySelectorAll<HTMLElement>('[data-counter]').forEach((el) => {
            const target = Number(el.dataset.counter ?? 0);

            if (!target || el.dataset.animated === 'true') {
                return;
            }

            el.dataset.animated = 'true';

            const duration = 1200;
            const start = performance.now();

            function tick(now: number): void {
                const progress = Math.min((now - start) / duration, 1);
                const value = Math.floor(progress * target);

                el.textContent = value.toLocaleString('pt-BR');

                if (progress < 1) {
                    requestAnimationFrame(tick);
                }
            }

            requestAnimationFrame(tick);
        });
    }

    let counterObserver: IntersectionObserver | undefined;

    onMounted(() => {
        window.addEventListener('scroll', handleScroll, { passive: true });
        handleScroll();

        counterObserver = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    animateCounters();
                }
            },
            { threshold: 0.3 },
        );

        const statsBlock = document.getElementById('landing-stats');

        if (statsBlock) {
            counterObserver.observe(statsBlock);
        }
    });

    onUnmounted(() => {
        window.removeEventListener('scroll', handleScroll);
        counterObserver?.disconnect();
    });

    return {
        isSticky,
        mobileMenuOpen,
        activeSection,
        showBackToTop,
        toggleMobileMenu,
        closeMobileMenu,
        scrollToTop,
    };
}
