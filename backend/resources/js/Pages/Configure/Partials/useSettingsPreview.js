// The images the tablet preview shows, following what is waiting in the form: a chosen file, a
// removal or a picked bundled background, else what is saved.
import { computed, onBeforeUnmount, ref, watch } from 'vue';

function useFileUrl(getFile) {
    const url = ref(null);
    watch(getFile, (file) => {
        if (url.value) URL.revokeObjectURL(url.value);
        url.value = file ? URL.createObjectURL(file) : null;
    });
    onBeforeUnmount(() => { if (url.value) URL.revokeObjectURL(url.value); });
    return url;
}

export function usePreviewImages(branding, advertisement, images, options) {
    const logoFile = useFileUrl(() => branding.logo);
    const backgroundFile = useFileUrl(() => branding.background_image);
    const adFile = useFileUrl(() => advertisement?.advertisement_image ?? null);

    return {
        logo: computed(() => (branding.remove_logo ? null : logoFile.value || images().logo.url)),
        background: computed(() => {
            if (branding.remove_background_image) return null;
            if (backgroundFile.value) return backgroundFile.value;
            if (branding.default_background) return options().backgrounds.find((b) => b.key === branding.default_background)?.url;
            return images().background.url;
        }),
        advertisement: computed(() => (advertisement?.remove_advertisement_image ? null : adFile.value || images().advertisement.url)),
    };
}
