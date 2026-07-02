<template>
    <nav v-if="sections.length" class="flex flex-wrap gap-2">
        <Link :href="`${base}/${mode}${eventQuery}`"
              :class="isAllActive ? 'catalog-pill catalog-pill--active' : 'catalog-pill'">
            All
        </Link>
        <Link v-for="sec in sections" :key="sec.slug"
              :href="`${base}/${mode}/${sec.slug}${eventQuery}`"
              :class="section?.slug === sec.slug ? 'catalog-pill catalog-pill--active' : 'catalog-pill'">
            {{ sec.label }}
        </Link>
    </nav>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    base: { type: String, required: true },
    mode: { type: String, required: true },
    sections: { type: Array, default: () => [] },
    section: { type: Object, default: null },
});

const page = usePage();

const eventQuery = computed(() => {
    const id = page.props.event?.id;
    return id ? `?event_id=${id}` : '';
});

const isAllActive = computed(() => !props.section || props.section.slug === 'all');
</script>
