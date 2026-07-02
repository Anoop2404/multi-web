<template>
    <PortalLayout
        role-label="House Admin Portal"
        :title="house?.name || school.name"
        :subtitle="user.name"
        accent="emerald"
        :nav-items="navItems"
    >
        <div class="space-y-4">
            <div v-if="house" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 text-sm">
                <p class="font-semibold text-emerald-900">{{ house.name }}</p>
                <p v-if="house.motto" class="text-emerald-700 text-xs mt-1">{{ house.motto }}</p>
                <p v-if="myHouseStats" class="mt-2 text-emerald-800">
                    Rank <strong>#{{ myHouseStats.rank }}</strong> · {{ myHouseStats.total_points }} points · {{ myHouseStats.participants }} participants
                </p>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div class="card">
                    <p class="text-xs text-gray-500 uppercase font-semibold">Students in my house</p>
                    <p class="text-3xl font-bold mt-1">{{ studentCount }}</p>
                </div>
                <div class="card">
                    <p class="text-xs text-gray-500 uppercase font-semibold">House rank</p>
                    <p class="text-3xl font-bold mt-1">#{{ myHouseStats?.rank ?? '—' }}</p>
                </div>
            </div>

            <div class="card">
                <h2 class="font-semibold mb-3">Intra-school house standings</h2>
                <ol class="divide-y text-sm">
                    <li v-for="row in houseRanking" :key="row.house_id" class="py-2 flex justify-between"
                        :class="house?.id === row.house_id ? 'font-semibold text-emerald-800' : ''">
                        <span>#{{ row.rank }} {{ row.house_name }}</span>
                        <span>{{ row.total_points }} pts</span>
                    </li>
                    <li v-if="!houseRanking.length" class="py-3 text-gray-400 text-center">No points yet.</li>
                </ol>
                <Link :href="`/portal/house-admin/${school.id}/ranking`" class="block mt-3 text-center text-sm text-emerald-700 font-semibold">
                    Full ranking →
                </Link>
            </div>

            <div class="card">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold">Recent fest registrations</h2>
                    <Link :href="`/portal/house-admin/${school.id}/registrations`" class="text-xs text-emerald-700 font-semibold">View all →</Link>
                </div>
                <ul class="divide-y text-sm">
                    <li v-for="reg in registrations" :key="reg.id" class="py-2">
                        <p class="font-medium">{{ reg.item?.title }}</p>
                        <p class="text-gray-500 text-xs">{{ reg.event?.title }} · {{ reg.status }}</p>
                    </li>
                    <li v-if="!registrations.length" class="py-3 text-gray-400 text-center">No registrations yet.</li>
                </ul>
            </div>

            <Link :href="`/portal/house-admin/${school.id}/students`"
                  class="btn-primary block w-full text-center py-3 rounded-xl font-semibold">
                View house students →
            </Link>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    school: Object,
    house: Object,
    user: Object,
    studentCount: Number,
    registrations: Array,
    houseRanking: Array,
    myHouseStats: Object,
    events: Array,
});

const navItems = computed(() => [
    { href: `/portal/house-admin/${props.school.id}`, label: 'Dashboard' },
    { href: `/portal/house-admin/${props.school.id}/students`, label: 'Students' },
    { href: `/portal/house-admin/${props.school.id}/registrations`, label: 'Registrations' },
    { href: `/portal/house-admin/${props.school.id}/ranking`, label: 'House ranking' },
]);
</script>
