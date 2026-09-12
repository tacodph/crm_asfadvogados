<script setup lang="ts">
import { ref } from 'vue';
import { faq } from '@/data/landing';
import { ChevronDown } from '@lucide/vue';

const faqAberta = ref(0);

function toggle(index: number): void {
    faqAberta.value = faqAberta.value === index ? -1 : index;
}
</script>

<template>
    <section id="faq" class="relative py-20">
        <div class="landing-container">
            <div class="mx-auto max-w-3xl">
                <h2 class="mb-8 text-center text-3xl capitalize leading-normal">
                    Perguntas que todo sócio faz
                </h2>
                <div class="flex flex-col gap-2">
                    <div
                        v-for="(item, index) in faq"
                        :key="item.pergunta"
                        class="landing-card overflow-hidden"
                    >
                        <button
                            type="button"
                            class="flex w-full cursor-pointer items-center justify-between gap-4 px-6 py-4 text-left"
                            :aria-expanded="faqAberta === index"
                            @click="toggle(index)"
                        >
                            <span class="text-15 font-medium text-slate-800">
                                {{ item.pergunta }}
                            </span>
                            <ChevronDown
                                class="size-4 shrink-0 text-custom-500 transition-transform"
                                :class="{ 'rotate-180': faqAberta === index }"
                            />
                        </button>
                        <div
                            v-show="faqAberta === index"
                            class="border-t border-slate-100 px-6 pb-4 pt-2 text-15 leading-relaxed text-slate-500"
                        >
                            {{ item.resposta }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
