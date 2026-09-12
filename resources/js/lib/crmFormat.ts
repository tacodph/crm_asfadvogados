export function brl(valor: number): string {
    return `R$ ${valor.toLocaleString('pt-BR')}`;
}

export function consentStyle(consent: string): { bg: string; cor: string } {
    if (consent === 'opt-in registrado') {
        return { bg: '#E7F0EE', cor: 'var(--accent)' };
    }

    if (consent === 'pendente') {
        return { bg: 'color-mix(in srgb, var(--primary) 12%, transparent)', cor: 'var(--primary)' };
    }

    return { bg: 'color-mix(in srgb, var(--destructive) 18%, transparent)', cor: '#9B3B2F' };
}
