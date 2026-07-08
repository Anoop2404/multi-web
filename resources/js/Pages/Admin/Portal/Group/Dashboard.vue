<template>
    <PortalLayout
        role-label="Group Admin Portal"
        :title="school.name"
        :subtitle="user.name"
        accent="violet"
        :nav-items="navItems"
    >
        <div class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="card">
                    <p class="text-xs text-gray-500 uppercase font-semibold">Students in my groups</p>
                    <p class="text-3xl font-bold mt-1">{{ studentCount }}</p>
                </div>
                <div class="card">
                    <p class="text-xs text-gray-500 uppercase font-semibold">Classes assigned</p>
                    <p class="text-3xl font-bold mt-1">{{ classes.length }}</p>
                </div>
            </div>

            <div class="card">
                <h2 class="font-semibold mb-3">My Classes</h2>
                <ul class="divide-y text-sm">
                    <li v-for="cls in classes" :key="cls.id" class="py-2 flex justify-between">
                        <span>{{ cls.name }}</span>
                        <span class="text-gray-400 text-xs">{{ cls.class_category?.label }}</span>
                    </li>
                    <li v-if="!classes.length" class="py-3 text-gray-400 text-center">
                        No classes assigned yet — contact school admin.
                    </li>
                </ul>
            </div>

            <Link :href="`/portal/group/${school.id}/students`"
                  class="btn-primary block w-full text-center py-3 rounded-xl font-semibold">
                View Students →
            </Link>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { groupPortalNavItems } from '@/support/groupPortalNav.js';

const props = defineProps({
    school: Object,
    user: Object,
    classes: Array,
    studentCount: Number,
});

const navItems = computed(() => groupPortalNavItems(props.school.id));
</script>
