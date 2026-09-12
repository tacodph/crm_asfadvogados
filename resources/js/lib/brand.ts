/**
 * Brand assets under public/images/logos.
 * LexStart is the product brand; tenant offices (e.g. ASF) remain data.
 */
export const BRAND = {
    name: 'LexStart',
    tagline: 'CRM jurídico',
    /** Colorful circular mark (transparent outside). */
    mark: '/images/logos/logo_circular_fundo_transparente.png',
    /** Circular mark on white square — safe on light chrome. */
    markOnWhite: '/images/logos/logo_circulas_fundo_branco.jpeg',
    /** Circular mark on dark square — WhatsApp / dark tiles. */
    markOnDark: '/images/logos/logo_circular_whatsApp.jpeg',
    /** Purple icon + LexStart wordmark — light backgrounds. */
    wordmarkLight: '/images/logos/logo_e_lexstarts.jpeg',
    /** Transparent PNG wordmark (purple) when a true alpha channel is needed. */
    wordmarkLightTransparent: '/images/logos/logo_e_lexstarts_transparente.png',
    /** Color icon + light wordmark — dark backgrounds. */
    wordmarkDark: '/images/logos/logo_circular_retangular.jpeg',
} as const;
