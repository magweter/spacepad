// Toasts: short messages at the bottom right that go away by themselves. Any page or component
// calls toast(); the shell draws them. Errors stay longer than confirmations.
import { reactive } from 'vue';

export const toasts = reactive([]);
let next = 0;

export function toast({ title, body = null, tone = 'neutral', timeout = null }) {
    const id = ++next;
    // The same message twice in a row (a poll failing every minute) shows once.
    if (toasts.some((t) => t.title === title && t.body === body)) return;
    toasts.push({ id, title, body, tone });
    if (toasts.length > 4) toasts.shift();
    setTimeout(() => dismiss(id), timeout ?? (tone === 'danger' ? 9000 : 5000));
}

export function toastError(body, title = 'Something went wrong') {
    toast({ title, body: body || 'Try again in a moment. If it keeps happening, email support@spacepad.io.', tone: 'danger' });
}

export function dismiss(id) {
    const i = toasts.findIndex((t) => t.id === id);
    if (i !== -1) toasts.splice(i, 1);
}
