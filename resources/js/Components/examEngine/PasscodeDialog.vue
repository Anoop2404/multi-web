<template>
    <Modal :show="show" title="New exam passcode" :subtitle="subtitle" size="sm" persistent @close="$emit('close')">
        <div class="space-y-4 text-center">
            <p class="text-sm text-slate-600">Read this to the candidate or send it privately. It is shown only once here.</p>
            <p class="select-all rounded-xl border border-slate-200 bg-slate-50 px-4 py-4 font-mono text-3xl font-bold tracking-widest text-slate-900">
                {{ passcode }}
            </p>
            <button type="button" class="btn-secondary text-sm" @click="copy">{{ copied ? 'Copied' : 'Copy passcode' }}</button>
            <p class="text-xs text-slate-500">The old passcode no longer works. Reprint the hall ticket if the candidate needs it on paper.</p>
            <p v-if="warning" class="rounded-lg bg-amber-50 px-3 py-2 text-left text-sm text-amber-900">{{ warning }}</p>
        </div>
        <template #footer>
            <button type="button" class="btn-primary text-sm" @click="$emit('close')">Done</button>
        </template>
    </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    passcode: { type: String, default: '' },
    row: { type: Object, default: null },
    warning: { type: String, default: '' },
});

defineEmits(['close']);

const copied = ref(false);
const subtitle = computed(() => (props.row ? `${props.row.name}${props.row.hall_ticket ? ` · HT ${props.row.hall_ticket}` : ''}` : ''));

watch(() => props.show, () => {
    copied.value = false;
});

async function copy() {
    try {
        await navigator.clipboard.writeText(props.passcode);
        copied.value = true;
    } catch {
        copied.value = false;
    }
}
</script>
