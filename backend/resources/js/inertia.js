import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { toastError } from './Components/Toasts/toasts';

// Errors nobody else handles end up as a toast instead of Inertia's full-screen error page or
// nothing at all: a server error, a lost connection, a script that throws.
router.on('invalid', (event) => {
    const status = event.detail.response?.status;
    if (status === 419) return; // an expired session: Inertia reloads the page itself
    event.preventDefault();
    toastError(status >= 500 ? 'The server could not finish this. Try again in a moment.' : null);
});
router.on('exception', (event) => {
    event.preventDefault();
    toastError('There is no connection to Spacepad. Check your internet and try again.', 'Connection lost');
});
window.addEventListener('unhandledrejection', (event) => {
    console.error(event.reason);
    toastError(event.reason?.message || null);
});

createInertiaApp({
    title: (title) => (title ? `${title} - Spacepad` : 'Spacepad'),
    // Each page is its own chunk, loaded when it is first opened, so the first visit does not
    // download every screen of the portal.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.config.errorHandler = (error) => {
            console.error(error);
            toastError(null);
        };
        app.use(plugin)
            .mount(el);
    },
    progress: {
        color: '#FDA312',
    },
});
