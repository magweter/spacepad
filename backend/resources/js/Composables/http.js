/**
 * A JSON request to the portal's own endpoints, with the CSRF token. Resolves with the response
 * body; rejects with an Error whose message is the server's own `message` when it sent one.
 */
export async function requestJson(method, url, { body, params } = {}) {
    const target = params ? `${url}?${new URLSearchParams(params)}` : url;
    const response = await fetch(target, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(data.message || '');

    return data;
}
