<template>
    <nav class="workflow-stepper" :aria-label="ariaLabel">
        <ol class="workflow-stepper__list">
            <li v-for="(step, index) in steps" :key="step.key"
                class="workflow-stepper__item"
                :class="{
                    'workflow-stepper__item--done': step.state === 'done',
                    'workflow-stepper__item--current': step.state === 'current',
                    'workflow-stepper__item--blocked': step.state === 'blocked',
                }">
                <component :is="step.href && step.state !== 'blocked' ? Link : 'span'"
                           :href="step.href"
                           class="workflow-stepper__link"
                           :class="{ 'workflow-stepper__link--static': !step.href || step.state === 'blocked' }">
                    <span class="workflow-stepper__badge" aria-hidden="true">
                        <span v-if="step.state === 'done'">✓</span>
                        <span v-else>{{ index + 1 }}</span>
                    </span>
                    <span class="workflow-stepper__text">
                        <span class="workflow-stepper__label">{{ step.label }}</span>
                        <span v-if="step.hint" class="workflow-stepper__hint">{{ step.hint }}</span>
                    </span>
                </component>
                <span v-if="index < steps.length - 1" class="workflow-stepper__connector" aria-hidden="true" />
            </li>
        </ol>
    </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    steps: { type: Array, default: () => [] },
    ariaLabel: { type: String, default: 'Progress' },
});
</script>
