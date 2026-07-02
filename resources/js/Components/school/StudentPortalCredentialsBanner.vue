<template>
    <div v-if="rows.length" class="card mb-6 border-l-4 border-l-emerald-500 bg-emerald-50/60 space-y-3">
        <div>
            <p class="text-sm font-semibold text-emerald-900">Student portal logins</p>
            <p class="text-xs text-emerald-800 mt-1">
                Students sign in at the portal with <strong>username</strong> (their reg. no.) and the temp password below.
                Passwords are shown once — share with students or parents.
            </p>
        </div>
        <div class="overflow-x-auto rounded-lg border border-emerald-200 bg-white">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Username</th>
                        <th>Temp password</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="i">
                        <td>{{ row.name }}</td>
                        <td class="font-mono text-xs">{{ row.username }}</td>
                        <td class="font-mono text-xs">
                            <span v-if="row.password">{{ row.password }}</span>
                            <span v-else class="text-slate-500">Already had login</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    credentials: { type: Array, default: null },
});

const page = usePage();

const rows = computed(() => {
    const fromProps = props.credentials;
    if (fromProps?.length) {
        return fromProps.filter((r) => r.created || r.password);
    }

    const fromFlash = page.props.flash?.studentPortalCredentials;
    return Array.isArray(fromFlash) ? fromFlash.filter((r) => r.created || r.password) : [];
});
</script>
