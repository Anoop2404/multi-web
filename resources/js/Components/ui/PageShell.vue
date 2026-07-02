<template>
    <div class="page-shell" :class="classExtra">
        <PageHeader v-if="title || $slots.header" v-bind="headerProps">
            <template v-if="$slots.actions" #actions>
                <slot name="actions" />
            </template>
            <template v-if="$slots.header">
                <slot name="header" />
            </template>
        </PageHeader>
        <slot />
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, default: '' },
    description: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
    maxWidth: { type: String, default: '' },
    classExtra: { type: String, default: '' },
});

const headerProps = computed(() => ({
    title: props.title,
    description: props.description,
    eyebrow: props.eyebrow,
}));
</script>
