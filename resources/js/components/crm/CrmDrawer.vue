<script setup lang="ts">
import { onMounted, onUnmounted } from 'vue';

defineProps<{
    title: string;
    subtitle: string;
    subtitleMono?: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const onKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        emit('close');
    }
};

onMounted(() => {
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <aside
        class="crm-drawer flex h-full w-[560px] shrink-0 flex-col border-l border-[#E3DFD6] bg-white"
    >
        <div
            class="flex items-start justify-between gap-3.5 border-b border-[#EEEBE4] px-[22px] pt-5 pb-4"
        >
            <div class="flex min-w-0 flex-col gap-1">
                <span
                    class="font-[family-name:var(--font-crm-display)] text-[21px] font-medium tracking-[-0.01em]"
                >
                    {{ title }}
                </span>
                <span
                    :class="
                        subtitleMono
                            ? 'font-[family-name:var(--font-crm-mono)] text-[11.5px] text-[#77808E]'
                            : 'text-[12.5px] text-[#77808E]'
                    "
                >
                    {{ subtitle }}
                </span>
            </div>
            <button
                type="button"
                class="grid size-[30px] shrink-0 cursor-pointer place-items-center rounded-[7px] border border-[#E3DFD6] bg-white text-[15px] leading-none text-[#77808E]"
                aria-label="Fechar"
                @click="emit('close')"
            >
                ×
            </button>
        </div>

        <div
            class="flex flex-1 flex-col gap-[18px] overflow-auto px-[22px] pt-[18px] pb-[22px]"
        >
            <slot />
        </div>

        <div
            v-if="$slots.footer"
            class="flex gap-[9px] border-t border-[#EEEBE4] bg-[#FBFAF7] px-[22px] py-3.5"
        >
            <slot name="footer" />
        </div>
    </aside>
</template>
