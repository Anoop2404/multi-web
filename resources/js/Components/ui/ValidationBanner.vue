<template>
    <div v-if="messages.length" class="validation-banner" role="alert" aria-live="assertive">
        <div class="validation-banner__icon" aria-hidden="true">!</div>
        <div class="min-w-0 flex-1">
            <p class="validation-banner__title">{{ title }}</p>
            <ul v-if="messages.length > 1" class="validation-banner__list">
                <li v-for="(msg, i) in messages" :key="i">{{ msg }}</li>
            </ul>
            <p v-else class="validation-banner__single">{{ messages[0] }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    title: { type: String, default: 'Please fix the following before continuing' },
    errors: { type: Object, default: null },
});

const page = usePage();

const messages = computed(() => {
    const bag = props.errors ?? page.props.errors ?? {};
    const list = [];

    for (const value of Object.values(bag)) {
        if (Array.isArray(value)) {
            list.push(...value.filter(Boolean));
        } else if (value) {
            list.push(String(value));
        }
    }

    return list;
});
</script>
