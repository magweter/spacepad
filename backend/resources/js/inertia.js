import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { toast, toastError } from './Components/Toasts/toasts';

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
// While Vite swaps code during development, components can fail for a moment; that is not an
// error to show anyone. In development the toast names the error, so it can be traced.
let hotUpdateAt = 0;
if (import.meta.hot) {
    import.meta.hot.on('vite:beforeUpdate', () => { hotUpdateAt = Date.now(); });
    import.meta.hot.on('vite:afterUpdate', () => { hotUpdateAt = Date.now(); });
}
const duringHotUpdate = () => Date.now() - hotUpdateAt < 3000;
const detail = (error) => (import.meta.env.DEV ? error?.message || String(error) : error?.message) || null;

window.addEventListener('unhandledrejection', (event) => {
    console.error(event.reason);
    if (duringHotUpdate()) return;
    toastError(detail(event.reason));
});

// Flash messages from the server ("Switched to workspace", "Display saved") are toasts, not a bar
// at the top of the page. Only on responses from the server: going back in history replays the
// old page and must not show its message again. The sign-in page keeps its error next to the form.
function flashToasts(page) {
    const flash = page?.props?.flash || {};
    if (page?.component === 'Auth/Login') return;
    if (flash.error) toast({ title: flash.error, tone: 'danger' });
    if (flash.info) toast({ title: flash.info });
    if (flash.success) toast({ title: flash.success, tone: 'success' });
}
router.on('success', (event) => flashToasts(event.detail.page));

createInertiaApp({
    title: (title) => (title ? `${title} - Spacepad` : 'Spacepad'),
    // Each page is its own chunk, loaded when it is first opened, so the first visit does not
    // download every screen of the portal.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        return pages[`./Pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        flashToasts(props.initialPage);
        const app = createApp({ render: () => h(App, props) });
        app.config.errorHandler = (error) => {
            console.error(error);
            if (duringHotUpdate()) return;
            toastError(import.meta.env.DEV ? detail(error) : null);
        };
        app.use(plugin)
            .mount(el);
    },
    progress: {
        color: '#FDA312',
    },
});
