<template>
    <nav v-if="sections.length" class="flex flex-wrap gap-2">
        <Link :href="`${allHref}${eventQuery}`"
              :class="isAllActive ? 'catalog-pill catalog-pill--active' : 'catalog-pill'">
            All
        </Link>
        <Link v-for="sec in sectionLinks" :key="sec.slug"
              :href="`${sec.href}${eventQuery}`"
              :class="section?.slug === sec.slug ? 'catalog-pill catalog-pill--active' : 'catalog-pill'">
            {{ sec.label }}
        </Link>
    </nav>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { sahodayaCatalogSectionHref, resolveCatalogProgramSlug } from '@/support/sahodayaPrograms.js';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    programSlug: { type: [String, Object], required: true },
    mode: { type: String, required: true },
    sections: { type: Array, default: () => [] },
    section: { type: Object, default: null },
});

const page = usePage();

const resolvedProgramSlug = computed(() => resolveCatalogProgramSlug(props.programSlug));

const eventQuery = computed(() => {
    const id = page.props.event?.id;
    return id ? `?event_id=${id}` : '';
});

const allHref = computed(() => sahodayaCatalogSectionHref(props.sahodayaId, resolvedProgramSlug.value, props.mode));

const sectionLinks = computed(() => props.sections.map((sec) => ({
    slug: sec.slug,
    label: sec.label,
    href: sahodayaCatalogSectionHref(props.sahodayaId, resolvedProgramSlug.value, props.mode, sec.slug),
})));

const isAllActive = computed(() => !props.section || props.section.slug === 'all');
</script>
