<template>
    <nav class="flex flex-wrap gap-2 border-b border-slate-200 pb-4 mb-4">
        <Link v-for="tab in tabs" :key="tab.key"
              :href="tab.href"
              :class="active === tab.key ? 'subnav-link subnav-link--active' : 'subnav-link'">
            {{ tab.label }}
        </Link>
    </nav>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    schoolId: { type: [String, Number], required: true },
    examId: { type: [String, Number], required: true },
    active: { type: String, required: true },
    resultsPublished: { type: Boolean, default: false },
});

const base = computed(() => `/school-admin/${props.schoolId}/mcq/${props.examId}`);

const tabs = computed(() => {
    const list = [
        { key: 'register', label: 'Register', href: `${base.value}/register` },
        { key: 'students', label: 'Students', href: `${base.value}/students` },
        { key: 'hall-tickets', label: 'Hall tickets', href: `${base.value}/hall-tickets` },
        { key: 'fee', label: 'Fee & payment', href: `${base.value}/fee` },
        { key: 'reports', label: 'Reports', href: `${base.value}/reports` },
    ];

    if (props.resultsPublished) {
        list.push(
            { key: 'results', label: 'Results', href: `${base.value}/results` },
            { key: 'toppers', label: 'Toppers', href: `${base.value}/toppers` },
        );
    }

    return list;
});
</script>
