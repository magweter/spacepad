import { router } from '@inertiajs/vue3';

function csrf() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Small helpers the wizard steps share: moving between steps and recording step events.
 */
export function useOnboarding(urls) {
    function goTo(step) {
        router.get(urls.step, { step }, { preserveScroll: false });
    }

    function track(step, event) {
        return fetch(urls.events, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf(),
            },
            body: JSON.stringify({ step, event }),
        }).catch(() => {});
    }

    async function completeAndGo(step, next) {
        await track(step, 'completed');
        goTo(next);
    }

    async function skipAndGo(step, next) {
        await track(step, 'skipped');
        goTo(next);
    }

    return { goTo, track, completeAndGo, skipAndGo, csrf };
}
