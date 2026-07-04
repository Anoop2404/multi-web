<template>
    <span class="inline-flex items-center gap-1 shrink-0" :title="tooltip">
        <span
            v-if="participantType && participantType !== 'individual'"
            class="fest-meta-icon fest-meta-icon--type"
            :class="typeClass"
            :title="typeLabel"
        >
            <svg v-if="participantType === 'team'" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M12 11v6"/><path d="M9 14h6"/>
            </svg>
        </span>
        <span
            v-if="normalizedGender && normalizedGender !== 'open'"
            class="fest-meta-icon fest-meta-icon--gender"
            :class="[genderClass, bare ? 'fest-meta-icon--bare' : '']"
            :title="genderLabelText"
            :aria-label="genderLabelText"
        >
            <span v-if="normalizedGender === 'male'" class="fest-gender-glyph" aria-hidden="true">♂</span>
            <span v-else-if="normalizedGender === 'female'" class="fest-gender-glyph" aria-hidden="true">♀</span>
            <span v-else class="fest-gender-glyph fest-gender-glyph--mixed" aria-hidden="true">⚥</span>
        </span>
        <span v-else-if="showOpenGender" class="fest-meta-icon fest-meta-icon--gender bg-slate-100 text-slate-500" title="Open gender">∅</span>
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { genderLabel, normalizeFestItemGender } from '@/support/festItemEligibility.js';

const props = defineProps({
    gender: { type: String, default: 'open' },
    participantType: { type: String, default: 'individual' },
    showOpenGender: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
    /** Drop the colored pill background — for use inside selected filter buttons. */
    bare: { type: Boolean, default: false },
});

const normalizedGender = computed(() => normalizeFestItemGender(props.gender));

const typeLabel = computed(() => {
    if (props.participantType === 'team') return 'Team event';
    if (props.participantType === 'group') return 'Group event';
    return 'Individual';
});

const genderLabelText = computed(() => {
    if (normalizedGender.value === 'mixed') return 'Mixed / Boys & Girls';
    return genderLabel(normalizedGender.value) ?? normalizedGender.value;
});

const typeClass = computed(() => {
    if (props.participantType === 'team') return 'bg-violet-100 text-violet-800';
    if (props.participantType === 'group') return 'bg-indigo-100 text-indigo-800';
    return 'bg-slate-100 text-slate-600';
});

const genderClass = computed(() => {
    if (props.bare) return 'text-current';
    if (normalizedGender.value === 'male') return 'bg-sky-100 text-sky-800';
    if (normalizedGender.value === 'female') return 'bg-pink-100 text-pink-800';
    if (normalizedGender.value === 'mixed') return 'bg-purple-100 text-purple-800';
    return 'bg-slate-100 text-slate-500';
});

const tooltip = computed(() => {
    const parts = [typeLabel.value];
    if (normalizedGender.value && normalizedGender.value !== 'open') parts.push(genderLabelText.value);
    return parts.join(' · ');
});
</script>

<style scoped>
.fest-meta-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.375rem;
    height: 1.375rem;
    border-radius: 9999px;
}

.fest-gender-glyph {
    font-size: 0.8125rem;
    line-height: 1;
    font-weight: 700;
}

.fest-gender-glyph--mixed {
    font-size: 0.75rem;
}

.fest-meta-icon--bare {
    width: auto;
    height: auto;
    background: transparent !important;
    padding: 0;
}
</style>
