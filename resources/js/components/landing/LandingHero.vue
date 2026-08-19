<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { heroColunas } from '@/data/landing';
import { login } from '@/routes';
</script>

<template>
    <section
        class="mx-auto grid max-w-[1180px] items-center gap-10 px-7 pt-[76px] pb-[22px] lg:grid-cols-[1.02fr_0.98fr] lg:gap-[52px]"
    >
        <div class="flex flex-col gap-6">
            <span
                class="inline-flex items-center gap-2 self-start rounded-full border border-[#E2D3B6] bg-[#F6EFE2] px-3 py-[5px] text-xs text-[#6F5730]"
            >
                <span class="h-1.5 w-1.5 rounded-full bg-[#14574F]" />
                Desenhado sobre o Provimento 205/2021 e a LGPD
            </span>

            <h1
                class="m-0 font-[family-name:var(--font-landing-display)] text-[40px] leading-[1.06] font-normal tracking-[-0.028em] text-pretty sm:text-[58px]"
            >
                O CRM para o que acontece
                <em class="italic">antes</em>
                do processo.
            </h1>

            <p
                class="m-0 max-w-[496px] text-[16.5px] leading-[1.68] text-pretty text-[#3C4450]"
            >
                Seu software jurídico cuida de prazos e tribunais. O Antessala
                cuida do momento comercial: da primeira mensagem no WhatsApp ao
                contrato assinado — com funis próprios da advocacia e trilha de
                auditoria em cada movimento.
            </p>

            <div class="flex flex-wrap items-center gap-[11px]">
                <a
                    href="#demonstracao"
                    class="rounded-[9px] bg-[#171B21] px-[22px] py-[13px] text-[14.5px] font-medium text-white transition-colors hover:bg-[#2B313A] hover:text-white"
                >
                    Agendar demonstração
                </a>
                <Link
                    :href="login()"
                    class="rounded-[9px] border border-[#D8D2C5] bg-white px-5 py-[13px] text-[14.5px] text-[#171B21] transition-colors hover:bg-[#FBFAF7]"
                >
                    Ver o produto por dentro →
                </Link>
            </div>

            <div class="flex flex-wrap gap-[26px] pt-1">
                <span class="text-[12.5px] text-[#77808E]">
                    Implantação em 2 semanas
                </span>
                <span class="text-[12.5px] text-[#77808E]">
                    Dados hospedados no Brasil
                </span>
                <span class="text-[12.5px] text-[#77808E]">
                    Sem importação de listas frias
                </span>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-[14px] border border-[#E3DFD6] bg-white shadow-[0_26px_60px_-32px_rgba(23,27,33,0.32)]"
        >
            <div
                class="flex items-center justify-between border-b border-[#EEEBE4] bg-[#FBFAF7] px-[14px] py-[11px]"
            >
                <span class="flex gap-1.5">
                    <span class="h-2 w-2 rounded-full bg-[#DCD7CC]" />
                    <span class="h-2 w-2 rounded-full bg-[#DCD7CC]" />
                    <span class="h-2 w-2 rounded-full bg-[#DCD7CC]" />
                </span>
                <span
                    class="font-[family-name:var(--font-landing-mono)] text-[9.5px] tracking-[0.1em] text-[#9AA2AE] uppercase"
                >
                    Funil B2B consultivo
                </span>
            </div>

            <div class="flex gap-2.5 bg-[#F7F5F0] px-[14px] py-4 pb-[18px]">
                <div
                    v-for="coluna in heroColunas"
                    :key="coluna.nome"
                    class="flex min-w-0 flex-1 flex-col overflow-hidden rounded-[9px] border border-[#E7E3DA] bg-[#F1EEE7]"
                >
                    <div
                        class="flex items-center justify-between gap-1.5 px-2.5 py-2"
                        :style="{ background: coluna.cor }"
                    >
                        <span
                            class="truncate text-[10.5px] font-semibold text-[#FBF9F4]"
                        >
                            {{ coluna.nome }}
                        </span>
                        <span
                            class="rounded-full bg-white/18 px-1.5 py-px font-[family-name:var(--font-landing-mono)] text-[9.5px] text-[#FBF9F4]"
                        >
                            {{ coluna.qtd }}
                        </span>
                    </div>
                    <div class="flex flex-col gap-[7px] p-2">
                        <div
                            v-for="card in coluna.cards"
                            :key="card.nome"
                            class="flex flex-col gap-[5px] rounded-[7px] border border-[#E3DFD6] bg-white px-[9px] py-2"
                        >
                            <span
                                class="text-[10.5px] leading-[1.3] font-medium text-[#171B21]"
                            >
                                {{ card.nome }}
                            </span>
                            <span
                                class="flex items-center justify-between gap-1.5"
                            >
                                <span
                                    class="rounded-full bg-[#F4F2EC] px-1.5 py-0.5 text-[9px] text-[#3C4450]"
                                >
                                    {{ card.canal }}
                                </span>
                                <span
                                    class="font-[family-name:var(--font-landing-mono)] text-[9.5px] text-[#171B21]"
                                >
                                    {{ card.valor }}
                                </span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
