export function brl(valor: number): string {
    return `R$ ${valor.toLocaleString('pt-BR')}`;
}

export function consentStyle(consent: string): { bg: string; cor: string } {
    if (consent === 'opt-in registrado') {
        return { bg: '#E7F0EE', cor: '#14574F' };
    }

    if (consent === 'pendente') {
        return { bg: '#FBF1DF', cor: '#8C6F3F' };
    }

    return { bg: '#F8ECE9', cor: '#9B3B2F' };
}
