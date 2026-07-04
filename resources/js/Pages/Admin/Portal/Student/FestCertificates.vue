<template>
    <PortalLayout
        role-label="Student Portal"
        title="Certificates"
        :subtitle="`${school.name} · ${student.reg_no}`"
        accent="indigo"
        :nav-items="navItems"
    >
        <section class="card">
            <h2 class="font-semibold text-sm mb-2">Fest Certificates</h2>
            <ul class="text-sm divide-y">
                <li v-for="(c, i) in festCerts" :key="i" class="py-2 flex justify-between items-center gap-2">
                    <div>
                        <p class="font-medium">{{ c.event?.title ?? 'Event' }} — {{ c.item?.title ?? c.student?.name }}</p>
                        <p v-if="c.mark?.grade || c.mark?.position" class="text-xs text-indigo-700 mt-0.5">
                            <span v-if="c.mark?.grade">Grade: {{ c.mark.grade }}</span>
                            <span v-if="c.mark?.position"> · Position: {{ c.mark.position }}</span>
                        </p>
                    </div>
                    <a v-if="c.uuid"
                       :href="`/certificates/print/${c.uuid}`"
                       target="_blank"
                       rel="noopener"
                       class="text-xs font-semibold text-indigo-600 shrink-0">Download ↗</a>
                </li>
                <li v-if="!festCerts?.length" class="text-gray-400 py-2">No certificates yet</li>
            </ul>
        </section>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';
import { studentPortalNavItems } from '@/support/studentPortalNav.js';

const props = defineProps({
    school: Object,
    student: Object,
    festCerts: { type: Array, default: () => [] },
});

const navItems = computed(() => studentPortalNavItems(props.school.id));
</script>
