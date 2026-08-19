import type { InertiaLinkProps } from '@inertiajs/vue3';
import { FONTES, FUNIS_INIT, type ScreenKey } from '@/data/crm';
import { index as contatos } from '@/routes/contatos';
import { index as empresas } from '@/routes/empresas';
import { index as negociacoes } from '@/routes/negociacoes';
import type { CrmCounts } from '@/types/crm';

export const PAGE_SCREEN: Record<string, ScreenKey> = {
    Dashboard: 'painel',
    'crm/Contatos': 'contatos',
    'crm/Empresas': 'empresas',
    'crm/Negociacoes': 'negociacoes',
};

/** Telas já ligadas ao sistema. O restante do menu permanece visível, mas desabilitado. */
export const SCREEN_HREF: Partial<
    Record<ScreenKey, NonNullable<InertiaLinkProps['href']>>
> = {
    contatos: contatos(),
    empresas: empresas(),
    negociacoes: negociacoes(),
};

export function isNavEnabled(key: ScreenKey): boolean {
    return SCREEN_HREF[key] !== undefined;
}

export const SCREEN_COUNT: Partial<Record<ScreenKey, string>> = {
    propostas: '9',
    automacoes: '4',
    trafego: String(
        FONTES.filter((fonte) => fonte.estado === 'conectado').length,
    ),
    compliance: '3',
    admin: String(FUNIS_INIT.length),
};

export function screenCount(
    key: ScreenKey,
    crmCounts?: CrmCounts | null,
): string | undefined {
    if (!isNavEnabled(key)) {
        return undefined;
    }

    if (key === 'contatos' || key === 'empresas' || key === 'negociacoes') {
        return crmCounts?.[key] ?? '0';
    }

    return SCREEN_COUNT[key];
}
