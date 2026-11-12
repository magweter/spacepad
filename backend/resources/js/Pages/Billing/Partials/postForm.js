// Checkout, billing portal and the yearly switch end on Lemon Squeezy, so they are posted as a real
// HTML form (with the csrf token) rather than through Inertia, which cannot follow an external
// redirect from a POST.
export function postForm(url, { target = null } = {}) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    if (target) {
        form.target = target;
        form.rel = 'noopener';
    }

    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    form.appendChild(token);

    document.body.appendChild(form);
    form.submit();
    form.remove();
}
