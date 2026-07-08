<template>
    <WorkflowStepper v-if="steps.length" :steps="steps" aria-label="Annual registration steps" class="mb-6" />
</template>

<script setup>
import { computed } from 'vue';
import WorkflowStepper from '@/Components/ui/WorkflowStepper.vue';
import { buildMembershipWorkflowSteps } from '@/support/membershipWorkflowSteps.js';

const props = defineProps({
    school: { type: Object, required: true },
    profile: { type: Object, default: null },
    registration: { type: Object, default: null },
    current: { type: String, default: 'overview' },
});

const steps = computed(() =>
    buildMembershipWorkflowSteps(props.school?.id, {
        profile: props.profile,
        registration: props.registration,
        current: props.current,
    }),
);
</script>
