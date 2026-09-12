import type { InertiaLinkProps } from '@inertiajs/vue3';
import { FUNIS_INIT, type ScreenKey } from '@/data/crm';
import { dashboard } from '@/routes';
import { index as adminIndex } from '@/routes/admin';
import { index as atividades } from '@/routes/atividades';
import { index as automacoes } from '@/routes/automacoes';
import { index as calendario } from '@/routes/calendario';
import { index as compliance } from '@/routes/compliance';
import { index as contatos } from '@/routes/contatos';
import { index as empresas } from '@/routes/empresas';
import { index as negociacoes } from '@/routes/negociacoes';
import { index as propostas } from '@/routes/propostas';
import { index as site } from '@/routes/site';
import { index as trafego } from '@/routes/trafego';
import { index as investimento } from '@/routes/trafego/investimento';
import type { CrmCounts } from '@/types/crm';

export const PAGE_SCREEN: Record<string, ScreenKey> = {
    Dashboard: 'painel',
    'crm/Contatos': 'contatos',
    'crm/ContatosCreate': 'contatos',
    'crm/ContatosEdit': 'contatos',
    'crm/Empresas': 'empresas',
    'crm/EmpresasCreate': 'empresas',
    'crm/EmpresasEdit': 'empresas',
    'crm/Negociacoes': 'negociacoes',
    'crm/NegociacoesCreate': 'negociacoes',
    'crm/NegociacoesEdit': 'negociacoes',
    'crm/Atividades': 'atividades',
    'crm/Calendario': 'calendario',
    'crm/Propostas': 'propostas',
    'crm/PropostasShow': 'propostas',
    'crm/Automacoes': 'automacoes',
    'crm/Trafego': 'trafego',
    'crm/TrafegoEventos': 'trafego',
    'crm/TrafegoDiagnostico': 'trafego',
    'crm/TrafegoInvestimento': 'investimento',
    'crm/Site': 'site',
    'crm/LandingExport': 'site',
    'crm/Compliance': 'compliance',
    'crm/admin/Index': 'admin',
    'crm/admin/FinalidadesConsentimento': 'admin',
    'crm/admin/FinalidadeConsentimentoEdit': 'admin',
    'crm/admin/StatusConsentimentos': 'admin',
    'crm/admin/StatusConsentimentoEdit': 'admin',
    'crm/admin/StatusComerciais': 'admin',
    'crm/admin/StatusComercialEdit': 'admin',
    'crm/admin/Setores': 'admin',
    'crm/admin/SetorEdit': 'admin',
    'crm/admin/CanaisContato': 'admin',
    'crm/admin/CanalContatoEdit': 'admin',
    'crm/admin/StatusConflitos': 'admin',
    'crm/admin/StatusConflitoEdit': 'admin',
    'crm/admin/TiposPessoa': 'admin',
    'crm/admin/TipoPessoaEdit': 'admin',
    'crm/admin/Funis': 'admin',
    'crm/admin/FunilEdit': 'admin',
    'crm/admin/EtapaFunilEdit': 'admin',
    'settings/Profile': 'configuracoes',
    'settings/Security': 'configuracoes',
    'settings/Appearance': 'configuracoes',
    'settings/Users': 'configuracoes',
    'settings/UserEdit': 'configuracoes',
};

/** Todas as telas do menu lateral estão navegáveis. */
const ENABLED_SCREENS = new Set<ScreenKey>([
    'painel',
    'negociacoes',
    'atividades',
    'calendario',
    'contatos',
    'empresas',
    'propostas',
    'automacoes',
    'trafego',
    'investimento',
    'site',
    'compliance',
    'admin',
]);

export function isNavEnabled(key: ScreenKey): boolean {
    return ENABLED_SCREENS.has(key);
}

export function screenHref(
    key: ScreenKey,
): NonNullable<InertiaLinkProps['href']> | undefined {
    switch (key) {
        case 'painel':
            return dashboard();
        case 'contatos':
            return contatos();
        case 'empresas':
            return empresas();
        case 'negociacoes':
            return negociacoes();
        case 'atividades':
            return atividades();
        case 'calendario':
            return calendario();
        case 'propostas':
            return propostas();
        case 'automacoes':
            return automacoes();
        case 'trafego':
            return trafego();
        case 'investimento':
            return investimento();
        case 'site':
            return site();
        case 'compliance':
            return compliance();
        case 'admin':
            return adminIndex();
        default:
            return undefined;
    }
}

export const SCREEN_COUNT: Partial<Record<ScreenKey, string>> = {
    automacoes: '4',
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

    if (
        key === 'contatos' ||
        key === 'empresas' ||
        key === 'negociacoes' ||
        key === 'propostas' ||
        key === 'trafego'
    ) {
        return crmCounts?.[key] ?? '0';
    }

    return SCREEN_COUNT[key];
}
