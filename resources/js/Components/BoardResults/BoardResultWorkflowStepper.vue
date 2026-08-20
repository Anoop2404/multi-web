<template>
    <WorkflowStepper v-if="steps.length" :steps="steps" aria-label="Board result progress" class="mb-6" />
</template>

<script setup>
import { computed } from 'vue';
import WorkflowStepper from '@/Components/ui/WorkflowStepper.vue';
import { buildBoardResultWorkflowSteps } from '@/support/boardResultWorkflowSteps.js';

const props = defineProps({
    boardResult: { type: Object, default: null },
    certificationPackage: { type: Object, default: null },
    certificationRequired: { type: Boolean, default: false },
});

const steps = computed(() =>
    buildBoardResultWorkflowSteps({
        boardResult: props.boardResult,
        certificationPackage: props.certificationPackage,
        certificationRequired: props.certificationRequired,
    }),
);
</script>
