export type FetchJsonOptions = Omit<RequestInit, 'headers'> & {
    headers?: Record<string, string>;
};

const getCsrfToken = (): string | null => {
    const element = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    );
    return element?.content ?? null;
};

export const fetchJson = async <T>(
    url: string,
    options: FetchJsonOptions = {},
): Promise<T> => {
    const csrf = getCsrfToken();

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
            ...(options.headers ?? {}),
        },
    });

    if (!response.ok) {
        const message = `Request failed: ${response.status}`;
        throw new Error(message);
    }

    return response.json() as Promise<T>;
};
