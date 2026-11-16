/**
 * reCAPTCHA v3 for the Inertia forms. The script is loaded by the root view
 * (RecaptchaV3::initJs) when a site key is configured; without one this returns null and the
 * backend does not require a token.
 */
export function useRecaptcha(siteKey) {
    return async function token(action) {
        if (!siteKey || typeof window.grecaptcha === 'undefined') {
            return null;
        }

        return new Promise((resolve) => {
            window.grecaptcha.ready(() => {
                window.grecaptcha.execute(siteKey, { action }).then(resolve, () => resolve(null));
            });
        });
    };
}
