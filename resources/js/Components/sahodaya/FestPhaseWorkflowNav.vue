<template>
    <WorkflowStepper v-if="steps.length" :steps="steps" aria-label="Phased conduct setup steps" class="mb-4" />
</template>

<script setup>
import { computed } from 'vue';
import WorkflowStepper from '@/Components/ui/WorkflowStepper.vue';
import { buildPhaseWorkflowSteps } from '@/support/festPhaseWorkflowSteps.js';

const props = defineProps({
    batches: { type: Array, default: () => [] },
    phases: { type: Array, default: () => [] },
});

const steps = computed(() => buildPhaseWorkflowSteps({ batches: props.batches, phases: props.phases }));
</script>
