<script setup>
// The current image, where it comes from, and a way to replace or remove it. The chosen file and
// the remove flag are v-models; nothing is sent until the section is saved.
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import Icon from '../../../Components/Icon.vue';

const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    url: { type: String, default: null },
    own: { type: Boolean, default: false },
    inheritedFrom: { type: String, default: null },
    hint: { type: String, default: null },
    cover: { type: Boolean, default: false },
    file: { type: Object, default: null },
    remove: { type: Boolean, default: false },
    error: { type: String, default: null },
});
const emit = defineEmits(['update:file', 'update:remove']);

const input = ref(null);
const previewUrl = ref(null);

watch(() => props.file, (file) => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
    previewUrl.value = file ? URL.createObjectURL(file) : null;
});
onBeforeUnmount(() => { if (previewUrl.value) URL.revokeObjectURL(previewUrl.value); });

const shown = computed(() => (props.remove ? null : previewUrl.value || props.url));
const source = computed(() => {
    if (props.file) return `${props.file.name}, saved with this section`;
    if (props.remove) return 'Removed when you save';
    if (!props.url) return 'None yet';
    if (!props.own && props.inheritedFrom) return `From profile ${props.inheritedFrom}`;
    return 'Uploaded';
});

function pick(e) {
    const file = e.target.files[0] || null;
    emit('update:file', file);
    if (file) emit('update:remove', false);
}
function clear() {
    if (props.file) {
        emit('update:file', null);
        if (input.value) input.value.value = '';
    } else {
        emit('update:remove', !props.remove);
    }
}
</script>

<template>
    <div class="flex flex-wrap items-start gap-x-3.5 gap-y-1.5">
        <span class="flex-[0_0_150px] pt-2 text-[13px] font-medium">{{ label }}</span>
        <div class="flex min-w-0 flex-[1_1_200px] flex-col gap-1">
            <div class="flex items-center gap-3 rounded-md border border-dashed border-offline-border px-3 py-2.5">
                <span class="flex h-10 w-14 flex-none items-center justify-center overflow-hidden rounded-[6px] bg-surface-raised">
                    <img v-if="shown" :src="shown" :alt="label" :class="cover ? 'h-full w-full object-cover' : 'max-h-full max-w-full object-contain'">
                    <Icon v-else name="upload" :size="16" class="text-muted" />
                </span>
                <span class="min-w-0 flex-1 truncate text-[13px]" :class="remove ? 'text-reserved-text' : 'text-ink-2'">{{ source }}</span>
                <input :id="id" ref="input" type="file" accept="image/png,image/jpeg,image/gif" class="sr-only" @change="pick">
                <label :for="id" class="cursor-pointer text-xs font-semibold text-navy hover:underline">{{ url || file ? 'Replace' : 'Upload' }}</label>
                <button v-if="file || (url && own)" type="button" class="text-xs font-semibold text-reserved-text hover:underline" @click="clear">
                    {{ file ? 'Clear' : remove ? 'Keep' : 'Remove' }}
                </button>
            </div>
            <span v-if="hint" class="text-xs text-muted">{{ hint }}</span>
            <span v-if="error" class="text-xs font-medium text-reserved-text">{{ error }}</span>
        </div>
    </div>
</template>
