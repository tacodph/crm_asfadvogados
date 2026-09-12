// Replaces the wayfinder route helpers (@/routes, @/routes/tenants) for the
// standalone landing build. Every CTA points at the live CRM; override the
// base URL with VITE_LANDING_CRM_URL when running `npm run build:landing`.

const BASE = (import.meta.env.VITE_LANDING_CRM_URL ?? 'https://crm-asfadvogados.test').replace(/\/$/, '');

type Options = { query?: Record<string, string | number | boolean | undefined | null> };

function qs(options?: Options): string {
    const pairs = Object.entries(options?.query ?? {})
        .filter(([, v]) => v !== undefined && v !== null && v !== '')
        .map(([k, v]) => `${k}=${encodeURIComponent(String(v))}`);

    return pairs.length ? `?${pairs.join('&')}` : '';
}

/** Accepts a string or a wayfinder-style { url } object and returns an absolute URL. */
export function toUrl(href: string | Record<string, unknown>): string {
    const raw = typeof href === 'string' ? href : String((href as { url?: string }).url ?? '#');

    if (/^(https?:|mailto:|tel:|#)/.test(raw)) {
        return raw;
    }

    return `${BASE}${raw.startsWith('/') ? '' : '/'}${raw}`;
}

export const login = (options?: Options): string => `${BASE}/login${qs(options)}`;

export const create = (options?: Options): string => `${BASE}/criar-conta${qs(options)}`;
