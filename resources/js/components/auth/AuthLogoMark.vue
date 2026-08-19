<script setup lang="ts">
withDefaults(
    defineProps<{
        compact?: boolean;
    }>(),
    {
        compact: false,
    },
);
</script>

<template>
    <div class="flex items-center gap-3">
        <span
            class="grid shrink-0 place-items-center font-[family-name:var(--font-auth-display)] tracking-[0.03em] text-[#FBF9F4]"
            :class="
                compact
                    ? 'h-[30px] w-[30px] rounded-lg border border-[#E7E3DA] bg-[#171B21] text-[13px]'
                    : 'h-10 w-10 rounded-[10px] border border-[#2B313A] text-[15px]'
            "
        >
            ASF
        </span>
        <span class="flex flex-col leading-[1.25]">
            <span
                class="font-[family-name:var(--font-auth-display)] tracking-[0.05em] uppercase"
                :class="
                    compact
                        ? 'text-[15px] text-[#171B21]'
                        : 'text-[17px] text-[#FBF9F4]'
                "
            >
                ASF Advogados
            </span>
            <span
                class="font-[family-name:var(--font-auth-mono)] text-[9.5px] tracking-[0.14em] uppercase"
                :class="compact ? 'text-[#9AA2AE]' : 'text-[#7C8593]'"
            >
                Associados
            </span>
        </span>
    </div>
</template>
