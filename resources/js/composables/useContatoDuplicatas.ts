import { useHttp } from '@inertiajs/vue3';
import { onUnmounted, ref, watch, type Ref } from 'vue';
import { duplicatas as duplicatasRoute } from '@/actions/App/Http/Controllers/ContatoController';

export type ContatoDuplicataMatch = {
    campo: string;
    motivo: string;
    mensagem: string;
    contato: {
        id: number;
        nome: string;
        email: string | null;
        telefone: string | null;
        cpf: string | null;
    };
};

type IdentifiersSource = {
    email: string;
    telefone: string;
    cpf: string;
};

export function useContatoDuplicatas(
    source: IdentifiersSource | Ref<IdentifiersSource>,
    ignoreId: number | null = null,
    debounceMs = 350,
) {
    const http = useHttp();
    const matches = ref<ContatoDuplicataMatch[]>([]);
    const checking = ref(false);
    let timer: ReturnType<typeof setTimeout> | null = null;
    let requestSeq = 0;

    const clearTimer = (): void => {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    };

    const consultar = async (): Promise<void> => {
        const data = 'value' in source ? source.value : source;
        const email = data.email.trim();
        const telefone = data.telefone.trim();
        const cpf = data.cpf.trim();

        if (!email && !telefone && !cpf) {
            matches.value = [];
            checking.value = false;

            return;
        }

        const seq = ++requestSeq;
        checking.value = true;

        try {
            await http.get(
                duplicatasRoute.url({
                    query: {
                        ...(email !== '' ? { email } : {}),
                        ...(telefone !== '' ? { telefone } : {}),
                        ...(cpf !== '' ? { cpf } : {}),
                        ...(ignoreId !== null ? { ignore: ignoreId } : {}),
                    },
                }),
                {
                    onSuccess: (response) => {
                        if (seq !== requestSeq) {
                            return;
                        }

                        const payload = response as {
                            duplicatas?: ContatoDuplicataMatch[];
                        };
                        matches.value = payload.duplicatas ?? [];
                    },
                    onError: () => {
                        if (seq !== requestSeq) {
                            return;
                        }

                        matches.value = [];
                    },
                },
            );
        } finally {
            if (seq === requestSeq) {
                checking.value = false;
            }
        }
    };

    watch(
        () => {
            const data = 'value' in source ? source.value : source;

            return [data.email, data.telefone, data.cpf] as const;
        },
        () => {
            clearTimer();
            timer = setTimeout(() => {
                void consultar();
            }, debounceMs);
        },
        { immediate: true },
    );

    onUnmounted(() => {
        clearTimer();
        requestSeq += 1;
    });

    return { matches, checking };
}
