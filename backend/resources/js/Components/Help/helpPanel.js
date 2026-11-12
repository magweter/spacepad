// Opening the Help & Roadmap panel from anywhere. Uses the same window event as the Blade panel
// ('open-faq' with detail.tab 'help' | 'roadmap') so both keep working while the Blade one exists.
export const OPEN_EVENT = 'open-faq';

export function openHelp(tab = 'help') {
    window.dispatchEvent(new CustomEvent(OPEN_EVENT, { detail: { tab } }));
}

// URLs of the help endpoints (routes help.data, support.ask, roadmap.vote, roadmap.suggest).
export const helpUrls = {
    data: '/help',
    ask: '/support/ask',
    suggest: '/roadmap/suggest',
    vote: (id) => `/roadmap/${encodeURIComponent(id)}/vote`,
};

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

// fetch with JSON in and out. Resolves to { ok, status, data }; validation errors (422) come back
// as data.errors, keyed by field.
export async function requestJson(url, { method = 'GET', body = null } = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (method !== 'GET') {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-TOKEN'] = csrf();
    }

    const response = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: body ? JSON.stringify(body) : null,
    });

    let data = null;
    try {
        data = await response.json();
    } catch {
        data = null;
    }

    return { ok: response.ok, status: response.status, data };
}

// First validation message for a field, or a general message for other failures.
export function errorFor(result, field) {
    if (result.status === 422) return result.data?.errors?.[field]?.[0] ?? null;
    if (result.status === 429) return 'Too many attempts. Please wait a moment and try again.';
    return 'Something went wrong. Please try again.';
}
