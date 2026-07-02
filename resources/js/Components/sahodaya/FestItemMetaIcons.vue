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
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
        </span>
        <span
            v-if="gender && gender !== 'open'"
            class="fest-meta-icon fest-meta-icon--gender"
            :class="genderClass"
            :title="genderLabelText"
        >
            <svg v-if="gender === 'male'" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <circle cx="10" cy="14" r="5"/><path d="M19 5l-5.5 5.5M19 5h-5M19 5v5"/>
            </svg>
            <svg v-else-if="gender === 'female'" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <circle cx="12" cy="9" r="5"/><path d="M12 14v7M9 18h6"/>
            </svg>
            <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <circle cx="8" cy="9" r="3"/><circle cx="16" cy="9" r="3"/><path d="M8 14a4 4 0 0 0-8 0v1h8v-1z" transform="translate(0 -1)"/><path d="M16 14a4 4 0 0 0-8 0" transform="translate(8 -1)"/>
            </svg>
        </span>
        <span v-else-if="showOpenGender" class="fest-meta-icon fest-meta-icon--gender bg-slate-100 text-slate-500" title="Open gender">∅</span>
    </span>
</template>

<script setup>
import { computed } from 'vue';
import { genderLabel } from '@/support/festItemEligibility.js';

const props = defineProps({
    gender: { type: String, default: 'open' },
    participantType: { type: String, default: 'individual' },
    showOpenGender: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const typeLabel = computed(() => {
    if (props.participantType === 'team') return 'Team event';
    if (props.participantType === 'group') return 'Group event';
    return 'Individual';
});

const genderLabelText = computed(() => {
    if (props.gender === 'mixed') return 'Mixed / Boys & Girls';
    return genderLabel(props.gender) ?? props.gender;
});

const typeClass = computed(() => {
    if (props.participantType === 'team') return 'bg-violet-100 text-violet-800';
    if (props.participantType === 'group') return 'bg-indigo-100 text-indigo-800';
    return 'bg-slate-100 text-slate-600';
});

const genderClass = computed(() => {
    if (props.gender === 'male') return 'bg-sky-100 text-sky-800';
    if (props.gender === 'female') return 'bg-pink-100 text-pink-800';
    if (props.gender === 'mixed') return 'bg-purple-100 text-purple-800';
    return 'bg-slate-100 text-slate-500';
});

const tooltip = computed(() => {
    const parts = [typeLabel.value];
    if (props.gender && props.gender !== 'open') parts.push(genderLabelText.value);
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
</style>
