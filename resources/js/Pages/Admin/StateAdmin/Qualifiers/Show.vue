<template>
    <AdminLayout title="Qualifier intake">
        <div class="max-w-5xl mx-auto space-y-4">
            <div class="flex justify-between items-start">
                <div>
                    <h1 class="text-xl font-semibold">Intake {{ intake.id }}</h1>
                    <p class="text-sm text-slate-500">Status: {{ intake.status }}</p>
                </div>
                <form v-if="intake.status === 'received'" @submit.prevent="approve">
                    <button class="btn-primary text-sm">Finalize: approve pending entries</button>
                </form>
            </div>
            <table class="w-full text-sm">
                <thead><tr class="text-left border-b"><th>Item</th><th>Student</th><th>School</th><th>Pos</th><th>Region</th><th>Status</th><th>Review</th></tr></thead>
                <tbody>
                    <tr v-for="e in intake.entries" :key="e.id" class="border-b">
                        <td>{{ e.item_code }} {{ e.item_name }}</td>
                        <td>{{ e.student_name }}</td>
                        <td>{{ e.school_id }}</td>
                        <td>{{ e.position }}</td>
                        <td>{{ e.partition_key }}</td>
                        <td><span class="capitalize font-semibold" :class="e.status === 'rejected' ? 'text-red-600' : e.status === 'approved' ? 'text-emerald-600' : 'text-amber-600'">{{ e.status }}</span></td>
                        <td>
                            <div v-if="intake.status === 'received'" class="flex gap-2">
                                <button type="button" class="text-xs text-emerald-700" @click="review(e, 'approved')">Approve</button>
                                <button type="button" class="text-xs text-red-700" @click="review(e, 'rejected')">Reject</button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ intake: Object, actionUrls: { type: Object, required: true } });

function approve() {
    router.post(props.actionUrls.approve, {}, { preserveScroll: true });
}

function review(entry, status) {
    router.post(`${props.actionUrls.reviewEntryBase}/${entry.id}/review`, { status }, { preserveScroll: true });
}
</script>
