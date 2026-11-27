import { ref, watch } from 'vue';

/**
 * A ref whose value is kept in this browser, so a choice such as cards or table survives a
 * refresh. Only values from `allowed` are restored; storage that is unavailable (a private
 * window, blocked site data) simply means the default.
 */
export function useRememberedRef(key, fallback, allowed) {
    const storageKey = `spacepad:${key}`;
    let initial = fallback;
    try {
        const saved = window.localStorage.getItem(storageKey);
        if (saved !== null && (!allowed || allowed.includes(saved))) initial = saved;
    } catch {
        // Storage not available: keep the default.
    }

    const value = ref(initial);
    watch(value, (next) => {
        try {
            window.localStorage.setItem(storageKey, next);
        } catch {
            // Not remembered this time; the choice still applies on this page.
        }
    });

    return value;
}
