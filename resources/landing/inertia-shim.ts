import { defineComponent, h } from 'vue';
import { toUrl } from './routes-shim';

// Minimal stand-ins for the Inertia bits the landing components use, so the
// page runs as plain static HTML. <Link> becomes a real <a>; <Head> is a
// no-op (the landing's <head> tags live in index.html).

export const Link = defineComponent({
    name: 'Link',
    inheritAttrs: false,
    props: {
        href: { type: [String, Object], default: '#' },
        as: { type: String, default: 'a' },
    },
    setup(props, { slots, attrs }) {
        return () => h('a', { ...attrs, href: toUrl(props.href) }, slots.default?.());
    },
});

export const Head = defineComponent({
    name: 'Head',
    setup: () => () => null,
});
