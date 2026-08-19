<script setup lang="ts">
import { ref } from 'vue';
import { faq } from '@/data/landing';

const faqAberta = ref(0);

function toggle(index: number) {
    faqAberta.value = faqAberta.value === index ? -1 : index;
}
</script>

<template>
    <section
        id="perguntas"
        class="mx-auto flex max-w-[880px] flex-col gap-[26px] px-7 pt-[68px] pb-5"
    >
        <h2
            class="m-0 font-[family-name:var(--font-landing-display)] text-[32px] leading-[1.14] font-normal tracking-[-0.02em] sm:text-[36px]"
        >
            Perguntas que todo sócio faz.
        </h2>
        <div class="flex flex-col">
            <div
                v-for="(item, index) in faq"
                :key="item.pergunta"
                class="flex flex-col border-t border-[#E7E3DA]"
            >
                <button
                    type="button"
                    class="flex cursor-pointer items-center justify-between gap-4 border-0 bg-transparent py-[18px] text-left"
                    :aria-expanded="faqAberta === index"
                    @click="toggle(index)"
                >
                    <span class="text-[15px] font-medium text-[#171B21]">
                        {{ item.pergunta }}
                    </span>
                    <span
                        class="shrink-0 font-[family-name:var(--font-landing-mono)] text-[15px] text-[#8C6F3F]"
                    >
                        {{ faqAberta === index ? '−' : '+' }}
                    </span>
                </button>
                <p
                    v-show="faqAberta === index"
                    class="m-0 pr-[60px] pb-5 text-sm leading-[1.7] text-[#3C4450]"
                >
                    {{ item.resposta }}
                </p>
            </div>
        </div>
    </section>
</template>
