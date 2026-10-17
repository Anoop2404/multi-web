<template>
    <Modal :show="show" :title="meta.title" :subtitle="subtitle" size="md" @close="close">
        <form id="attempt-action-form" class="space-y-4" @submit.prevent="submit">
            <p class="text-sm text-slate-600">{{ meta.description }}</p>

            <div v-if="action === 'extend'" class="space-y-2">
                <label class="form-label" for="extend-minutes">Extra minutes</label>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="option in quickMinutes"
                        :key="option"
                        type="button"
                        class="rounded-md border px-3 py-1.5 text-sm font-semibold"
                        :class="minutes === option ? 'border-[color:var(--brand-navy)] bg-[color:var(--brand-navy)] text-white' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                        @click="minutes = option"
                    >
                        {{ option }} min
                    </button>
                    <input id="extend-minutes" v-model.number="minutes" type="number" min="1" :max="maxMinutes" class="field field--sm !w-24" required>
                </div>
                <p v-if="extendCap !== null" class="form-hint text-xs text-slate-500">
                    Schools can give up to {{ extendCap }} minutes in total per candidate; {{ extendLeft }} left for this candidate.
                    For more, ask the Sahodaya exam team.
                </p>
            </div>

            <div class="space-y-1">
                <label class="form-label" for="action-reason">Reason<span v-if="!meta.reasonRequired" class="font-normal text-slate-400"> (optional)</span></label>
                <textarea
                    id="action-reason"
                    v-model="reason"
                    class="field"
                    rows="2"
                    maxlength="500"
                    :required="meta.reasonRequired"
                    :placeholder="meta.placeholder"
                />
                <p class="text-xs text-slate-400">Saved in the activity log with your name.</p>
            </div>

            <p v-if="error" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">{{ error }}</p>
        </form>

        <template #footer>
            <button type="button" class="btn-secondary text-sm" :disabled="busy" @click="close">Cancel</button>
            <button
                type="submit"
                form="attempt-action-form"
                class="btn-primary text-sm"
                :class="meta.danger ? '!bg-red-600 hover:!bg-red-700' : ''"
                :disabled="busy"
            >
                {{ busy ? 'Working…' : meta.confirmLabel }}
            </button>
        </template>
    </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useConfirm } from '@/composables/useConfirm.js';
import { postJson } from './liveMonitorApi.js';

const props = defineProps({
    show: { type: Boolean, default: false },
    action: { type: String, default: '' },
    row: { type: Object, default: null },
    url: { type: String, default: '' },
    extendCap: { type: Number, default: null },
});

const emit = defineEmits(['close', 'done']);
const { confirm } = useConfirm();

const minutes = ref(5);
const reason = ref('');
const busy = ref(false);
const error = ref('');

const META = {
    extend: {
        title: 'Give extra time',
        description: 'Moves this candidate\'s deadline. Use it for power cuts, device failures or connection problems.',
        confirmLabel: 'Add time',
        reasonRequired: true,
        placeholder: 'e.g. power cut at home for 10 minutes',
    },
    allow_switch: {
        title: 'Allow device switch',
        description: 'Lets the candidate log in once from another device. Their answers so far carry over; the old device is signed out.',
        confirmLabel: 'Allow switch',
        reasonRequired: false,
        placeholder: 'e.g. phone battery died, moving to a laptop',
    },
    unlock: {
        title: 'Unlock attempt',
        description: 'The candidate can continue writing straight away.',
        confirmLabel: 'Unlock',
        reasonRequired: false,
        placeholder: 'e.g. identity confirmed by the invigilator',
    },
    lock: {
        title: 'Lock attempt',
        description: 'Holds the attempt: the candidate sees a "paused" screen and no answers are accepted until it is unlocked. The clock keeps running.',
        confirmLabel: 'Lock attempt',
        reasonRequired: true,
        danger: true,
        placeholder: 'e.g. suspected impersonation — verifying',
    },
    force_submit: {
        title: 'Force submit',
        description: 'Ends this attempt now and submits the answers saved so far. This cannot be undone.',
        confirmLabel: 'Force submit',
        reasonRequired: true,
        danger: true,
        placeholder: 'e.g. malpractice reported by the invigilator',
    },
};

const meta = computed(() => META[props.action] ?? { title: 'Action', description: '', confirmLabel: 'Confirm', reasonRequired: false });
const subtitle = computed(() => (props.row ? `${props.row.name}${props.row.hall_ticket ? ` · HT ${props.row.hall_ticket}` : ''}` : ''));
const extendLeft = computed(() => (props.extendCap === null ? null : (props.row?.extend_left ?? props.extendCap)));
const maxMinutes = computed(() => (props.extendCap === null ? 240 : Math.max(1, extendLeft.value ?? 0)));
const quickMinutes = computed(() => [5, 10, 15, 30].filter((m) => m <= maxMinutes.value));

watch(() => props.show, (open) => {
    if (open) {
        minutes.value = Math.min(5, maxMinutes.value);
        reason.value = '';
        error.value = '';
        busy.value = false;
    }
});

function close() {
    if (!busy.value) emit('close');
}

async function submit() {
    error.value = '';
    if (props.action === 'force_submit') {
        const ok = await confirm({
            title: 'Force submit this attempt?',
            message: `${props.row?.name ?? 'The candidate'}'s attempt ends now with the answers saved so far. This cannot be undone.`,
            confirmLabel: 'Yes, force submit',
            destructive: true,
        });
        if (!ok) return;
    }

    busy.value = true;
    try {
        const body = { action: props.action, reason: reason.value.trim() || null };
        if (props.action === 'extend') body.minutes = Number(minutes.value);
        const json = await postJson(props.url, body);
        emit('done', json);
    } catch (e) {
        error.value = e.message;
    } finally {
        busy.value = false;
    }
}
</script>
